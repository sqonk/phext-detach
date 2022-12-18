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
 * A Channel is a loose implementation of channels from the Go language. It provides a simple way of allowing 
 * independent processes to send and receive data between one another.
 * 
 * A Channel is a block-in, and (by default) a block-out mechanism, meaning that the task that sets a value 
 * will block until another task has received it.
 * 
 * Bare in mind, that unlike BufferedChannels, for most situations a Channel should be explicitly closed off
 * when there is no more data to send, particularly when other tasks might be locked in a loop or waiting
 * indefinitely for more values. BufferedChannels have the ability to signal their closure prior to script
 * termination but a normal Channel does not, meaning that they have the potential to leave spawned subprocesses 
 * hanging after the parent script has since terminated if they are never closed.
 */
class Channel implements \IteratorAggregate
{    
    private const CHAN_SIG_CLOSE = "#__CHAN-CLOSE__#";
    private bool $open = true;
    protected string $key;
    
    /**
     * Construct a new Channel.
     */
	public function __construct()
	{
        $this->key = "CHANID-".uniqid();
	}

    protected function _synchronised(callable $callback): void
    {
        $lock = "{$this->key}.lock";
        $pid = detach_pid();
        while (apcu_fetch($lock) != $pid && $this->open())
        { 
            if (! apcu_add($lock, $pid))
                usleep(TASK_WAIT_TIME);
        }
        
        $callback();
        
        apcu_delete($lock);
    }
    
    /**
     * Test to see if the channel is currently open. 
     * 
     * @return bool TRUE if the channel is open, FALSE if not.
     */
    public function open(): bool
    {
        if ($this->open && apcu_exists($this->key)) {
            $this->open = (apcu_fetch($this->key) != self::CHAN_SIG_CLOSE);
        }
        return $this->open;
    }
    
    /**
     * Close off the channel, signalling to the receiver that no further values will be sent.
     */
    public function close(): self
    {
        if ($this->open())
        {
            $written = false;
            while (! $written)
            {
                $this->_synchronised(function() use (&$written) {
                    if (apcu_add($this->key, self::CHAN_SIG_CLOSE))
                        $written = true;
                });
                if (! $written)
                    usleep(TASK_WAIT_TIME); 
            }
            
            $this->open = false;
        }    
        
        return $this;
    }
		
    /**
     * Pass a value into the channel. This method will block until the
     * channel is free to receive new data again.
     * 
     * If the channel is closed then it will return immediately.
     */
    public function set(mixed $value): self
    {
        if (! $this->open())
            return $this;
        
        $written = false;
        while (! $written)
        {
            $this->_synchronised(function() use ($value, &$written) {
                if (apcu_add($this->key, $value))
                    $written = true;
            });
            if (! $written)
                usleep(TASK_WAIT_TIME); 
        }
        
        
        // Wait for the value to be read by something else.
        if ($value != self::CHAN_SIG_CLOSE) {
            while ($this->open() && apcu_exists($this->key)) {
                usleep(TASK_WAIT_TIME);    
            }
        }

        return $this;
    }
	
	/**
	 * Alias for Channel::set().
	 */
	public function put(mixed $value): self
	{
		$this->set($value);
		return $this;
	}

    /**
     * Obtain the next value on the channel (if any). If $wait is TRUE then
     * this method will block until a new value is received. Be aware that
     * in this mode the method will block forever if no further values
     * are sent from other tasks.
     * 
     * If $wait is given as an integer of 1 or more then it is used as a timeout
     * in seconds. In such a case, if nothing is received before the timeout then
     * a value of NULL will be returned.
     * 
     * $wait defaults to TRUE.
     */
    public function get(bool|int $wait = true): mixed
    {
        if (! $this->open)
            return CHAN_CLOSED;
        
		$value = null;
        $started = time();
        $waitTimeout = 0; 
        if (is_int($wait)) {
            $waitTimeout = $wait;
            $wait = true;
        }
        
        /*
            - Wait until data is present.
            - Acquire lock.
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
                        $value = apcu_fetch($this->key);
                        if ($value != self::CHAN_SIG_CLOSE)
                            apcu_delete($this->key);
                        $read = true;
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
	public function next(bool|int $wait = true): mixed {
		return $this->get($wait);
	}
    
    /**
     * Yield the channel out to an iterator loop until the point at which it is closed off. If you 
     * wish to put your task into an infinite scanning loop for the lifetime of the channel, 
     * for example to process all incoming data, then this can provide a more simplistic model for
     * doing so.
     * 
     * -- parameters:
     * @param $wait If $wait is given as an integer of 1 or more then it is used as a timeout in seconds. In such a case, if nothing is received before the timeout then a value of NULL will be returned if nothing is received prior to the expiry. Defaults to TRUE, which means each loop will block until such time as data is received.
     */
    public function incoming(bool|int $wait = true): \Generator
    {
        while (($value = $this->get($wait)) !== CHAN_CLOSED) {
            yield $value;
        }
    }
    
    /**
     * Use the channel object as an iterator for incoming values, looping until it is closed off. This method 
     * has the same effect as calling Channel::incoming() with the default parameter of TRUE for the $wait parameter.
     */
    public function getIterator(): \Traversable {
        return $this->incoming();
    }
}