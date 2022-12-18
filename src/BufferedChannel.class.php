<?php
namespace sqonk\phext\detach;

/**
*
* Threading
* 
* @package		phext
* @subpackage	detach
* @version		1
* 
* @license		MIT see license.txt
* @copyright	2019 Sqonk Pty Ltd.
*
*
* This file is distributed
* on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
* express or implied. See the License for the specific language governing
* permissions and limitations under the License.
*/

/**
 * A BufferedChannel is an queue of values that may be passed between tasks. Unlike a standard channel, 
 * it may continue to accept new values before any existing ones have been read in via another task.
 * 
 * The queue is unordered, meaning that values may be read in in a different order from that of which 
 * they were put in.
 * 
 * BufferedChannels are an effective bottle-necking system where data obtained from multiple tasks may 
 * need to be fed into a singular thread for post-processing.
 */
use sqonk\phext\core\arrays;

class BufferedChannel implements \IteratorAggregate
{
    protected bool $open = true;
    protected string $key;
    protected string $wckey;
    protected string $capkey;
    protected string $createdOnPID;
    
    private const CHAN_SIG_CLOSE = "#__CHAN-CLOSE__#";
    				
    /**
     * Construct a new BufferedChannel.
     */                
	public function __construct()
	{
		$this->key = 'BCHAN-'.uniqid();
        $this->wckey = "$this->key.wc";
        $this->capkey = "$this->key.cap";
        $this->createdOnPID = detach_pid();
	}
    
    public function __destruct()
    {
        if ($this->open and $this->createdOnPID == detach_pid()) {
            $this->close(); // close off channel from task that created it.
        }
    }
    
    protected function _synchronised(callable $callback): void
    {
        $lock = "{$this->key}.lock";
        $pid = detach_pid();
        while (apcu_fetch($lock) != $pid)
        { 
            if (! apcu_add($lock, $pid))
                usleep(TASK_WAIT_TIME);
        }
        
        $callback();
        
        apcu_delete($lock);
    }
	
    /**
     * Set an arbitrary limit on the number of times data will be read
     * from the channel. Once the limit has been reached the channel
     * will be closed.
     * 
     * Every time this method is called it will reset the write count to 0.
     */
	public function capacity(int $totalDeposits): BufferedChannel
	{
		apcu_store($this->wckey, 0);
        apcu_store($this->capkey, $totalDeposits);
        return $this;
	}
    
    protected function _writeCount(): int
    {
        $count = 0;
        if (apcu_exists($this->wckey)) {
            $v = apcu_fetch($this->wckey, $pass);
            if ($pass)
                $count = $v;
        }
        return $count;
    }
    
    protected function _increment(int $amount = 1): void
    { 
        if (apcu_exists($this->capkey)) {
            $current = $this->_writeCount();
            if (! apcu_store($this->wckey, $current+$amount))
                println('failed to write inc');
        }
    }
    
    protected function _hitCapcity(): bool
    {
        if (apcu_exists($this->capkey)) {
            $v = apcu_fetch($this->capkey, $ok);
            if ($ok) {
                return $this->_writeCount() >= $v;
            }
        }
        return false;
    }
    
    /**
     * Close off the channel, signalling to the receiver that no further values will be sent.
     */
    public function close(): self
    {
        if ($this->open)
            $this->set(self::CHAN_SIG_CLOSE);
        
        return $this;
    }
	
    /**
     * Queue a value onto the channel, causing all readers to wake up.
     */
    public function set($value): self
    { 
        /*
            Rules:
            - Require lock.
            - Append new data.
            - Release lock.
        */
        $written = false;
        while (! $written)
        {
            $this->_synchronised(function() use ($value, &$written) {
                $data = apcu_exists($this->key) ? apcu_fetch($this->key) : [];
                $data[] = $value;
                
                if (apcu_store($this->key, $data)) { 
                    $this->_increment();
                    $written = true;
                }
                    
            });
            if (! $written)
                usleep(TASK_WAIT_TIME); 
        }
        
        if ($written && $value !== self::CHAN_SIG_CLOSE && $this->_hitCapcity())
            $this->close();
                
        return $this;
    }
	
	/**
	 * Alias for Channel::set().
	 */
	public function put($value): self
	{
		return $this->set($value);
	}
    
    /**
     * Queue a bulk set of values onto the channel, causing all readers to wake up.
     * 
     * If you have a large number of items to push onto the queue at once then this
     * method will be faster than calling set() for every element in the array.
     * 
     * -- parameters:
     * @param array<mixed> $values The dataset to store.
     */
    public function bulk_set(array $values): self
    {
        /*
            Rules:
            - Require lock.
            - Append new data.
            - Release lock.
        */
        $written = false;
        while (! $written)
        {
            $this->_synchronised(function() use ($values, &$written) {
                $data = apcu_exists($this->key) ? apcu_fetch($this->key) : [];
                $data = array_merge($data, $values);
                
                if (apcu_store($this->key, $data)) {
                    $written = true;
                    $this->_increment(count($values));
                }
                    
            });
            if (! $written)
                usleep(TASK_WAIT_TIME); 
        }
        
        if ($written && $this->_hitCapcity())
            $this->close();
                
        return $this;
    }

    /**
     * Obtain the next value on the queue (if any). If $wait is TRUE then
     * this method will block until a new value is received. Be aware that
     * in this mode the method will block forever if no further values
     * are queued from other tasks.
     * 
     * If $wait is given as an integer of 1 or more then it is used as a timeout
     * in seconds. In such a case, if nothing is received before the timeout then
     * a value of NULL will be returned if nothing is received
     * prior to the expiry.
     * 
     * --parameters:
     * @param int|bool $wait If TRUE then block indefinitely until a new value is available. If FALSE then return immediately if there is nothing available. If a number is passed then wait the given number of seconds before giving up. Passing 0 is equivalent to passing TRUE. Passing a negative number will throw an exception.
     * 
     * @return mixed The next available value, NULL if none was available or a wait timeout was reached. If the channel was closed then the constant CHAN_CLOSED is returned.
     */
    public function get(int|bool $wait = true): mixed
    {
		if (! $this->open) 
		    return CHAN_CLOSED;
		
		$value = null;
        $started = time();
        $waitTimeout = 0; 
        if (is_int($wait)) {
            if ($wait < 0)
                throw new \Exception("Supplied integer for parameter $wait was less than 0.");
            
            $waitTimeout = $wait;
            $wait = true;
        }
        
        /*
            - Wait until data is present.
            - Aquire lock.
            - Read data, as long as someone else has not snuck in a got it since we got the lock.
            - Delete value
            - Release lock
        */
        $read = false;
        while (! $read)
        {
            if ($waitTimeout > 0 and time()-$started >= $waitTimeout)
                break;
            
            if (apcu_exists($this->key))
            {
                $this->_synchronised(function() use (&$value, &$read) {
                    if (apcu_exists($this->key)) {
                        $values = apcu_fetch($this->key); 
                        if (count($values) > 0) {
                            $value = array_shift($values); 
                            if ($value != self::CHAN_SIG_CLOSE) {
                                apcu_store($this->key, $values); // re-insert the shortened array.
                            }
                            $read = true;
                        }
                    }
                });
            }
            if (! $wait)
                $read = true;
            if (! $read)
                usleep(TASK_WAIT_TIME); 
        }
        
        if ($value == self::CHAN_SIG_CLOSE)
        {
            $value = CHAN_CLOSED;
            $this->open = false;
        }
        
        return $value;
    }
	
	/**
	 * Alias for Channel::get().
	 */
	public function next(int|bool $wait = true): mixed {
		return $this->get($wait);
	}
    
    /**
     * Obtain all values currently residing on the queue (if any). If $wait is TRUE then
     * this method will block until a new value is received. Be aware that
     * in this mode the method will block forever if no further values
     * are queued from other tasks.
     * 
     * If $wait is given as an integer of 1 or more then it is used as a timeout
     * in seconds. In such a case, if nothing is received before the timeout then
     * a value of NULL will be returned.
     * 
     * --parameters:
     * @param int|bool $wait If TRUE then block indefinitely until a new value is available. If FALSE then return immediately if there is nothing available. If a number is passed then wait the given number of seconds before giving up. Passing 0 is equivalent to passing TRUE. Passing a negative number will throw an exception.
     * 
     * @return array<mixed> The next available value, NULL if none was available or a wait timeout was reached. If the channel was closed then the constant CHAN_CLOSED is returned.
     */
    public function get_all(int|bool $wait = true) : ?array
    {
		if (! $this->open)
			return CHAN_CLOSED;
		
		$values = null;
        $started = time();
        $waitTimeout = 0; 
        if (is_int($wait)) {
            $waitTimeout = $wait;
            $wait = true;
        }
        
        /*
            - Wait until data is present.
            - Aquire lock.
            - Read data, as long as someone else has not snuck in a got it since we got the lock.
            - Delete value
            - Release lock
        */
        $read = false;
        while (! $read)
        {
            if ($waitTimeout > 0 and time()-$started >= $waitTimeout)
                break;
            
            if (apcu_exists($this->key))
            {
                $this->_synchronised(function() use (&$values, &$read) {
                    if (apcu_exists($this->key)) { 
                        $values = apcu_fetch($this->key); 
                        $read = true; 
                        apcu_delete($this->key); 
                        if (is_array($values) && arrays::last($values) == self::CHAN_SIG_CLOSE) {
                            array_pop($values);
                            $this->open = false;
                            // re-insert close sig so other listeners can pick it up.
                            apcu_store($this->key, [self::CHAN_SIG_CLOSE]);
                        }
                    }
                });
            }
            if (! $wait)
                $read = true;
            if (! $read)
                usleep(TASK_WAIT_TIME); 
        }
        
        return $values;
    }
    
    /**
     * Yield the channel out to an iterator loop until the point at which it is closed off. If you 
     * wish to put your task into an infinite scanning loop for the lifetime of the channel, 
     * for example to process all incoming data, then this can provide a more simplistic model for
     * doing so.
     * 
     * -- parameters:
     * @param int|bool $wait If TRUE then block indefinitely until a new value is available. If FALSE then return immediately if there is nothing available. If a number is passed then wait the given number of seconds before giving up. Passing 0 is equivalent to passing TRUE. Passing a negative number will throw an exception.
     */
    public function incoming(int|bool $wait = true): \Generator
    {
        while (($value = $this->get($wait)) !== CHAN_CLOSED) {
            yield $value;
        }
    }
    
    /**
     * Use the channel object as an iterator for incoming values, looping until it is closed off. This method 
     * has the same effect as calling BufferedChannel::incoming() with the default parameter of TRUE for the $wait parameter.
     */
    public function getIterator(): \Traversable {
        return $this->incoming();
    }
}