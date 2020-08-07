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

/*
A BufferedChannel is an queue of values that may be passed between tasks. Unlike a standard channel, it may continue to accept new values before any existing ones have been read in via another task.

The queue is unordered, meaning that values may be read in in a different order from that of which they were put in.

BufferedChannels are an effective bottle-necking system where data obtained from multiple tasks may need to be fed into a singular thread for post-processing.
*/
use sqonk\phext\core\strings;

class BufferedChannel
{
	protected $capacity;
	protected $readCount = 0;
    protected $open = true;
    
    private const CHAN_SIG_CLOSE = "#__CHAN-CLOSE__#";
    
    static private $storeLoc;
				
	public function __construct()
	{
		$this->key = 'BCHAN-'.uniqid();
	}
    
    protected function _synchronised($callback)
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
	
	/*
		Set an arbitrary limit on the number of times data will be ready 
		from the channel. Once the limit has been reached all subsequent 
		reads will return FALSE.
	
		Every time this method is called it will reset the read count to 0.
	*/
	public function capacity(int $totalDeposits)
	{
		$this->capacity = $totalDeposits;
		$this->readCount = 0;
        return $this;
	}
    
    // Close off the channel, signalling to the receiver that no further values will be sent.
    public function close()
    {
        if (! $this->open)
            return;
        
        $this->set(self::CHAN_SIG_CLOSE);
        $this->open = false;
    }
	
    /* 
		Queue a value onto the channel, causing all readers to wake up.
	*/
    public function set($value) 
    { 
        if (! $this->open)
            return;
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
                
                if (apcu_store($this->key, $data))
                    $written = true;
            });
            if (! $written)
                usleep(TASK_WAIT_TIME); 
        }
                
        return $this;
    }
	
	// Alias for Channel::set().
	public function put($value)
	{
		return $this->set($value);
	}

    /*
		Obtain the next value on the queue (if any). If $wait is TRUE then
		this method will block until a new value is received. Be aware that
		in this mode the method will block forever if no further values
		are queued from other tasks.
	
		If the read capacity of the channel is set and has been exceeded then
		this method will return FALSE immediately.
    
        If $wait is given as an integer of 1 or more then it is used as a timeout
        in seconds. In such a case, if nothing is received before the timeout then 
        a value of NULL will be returned if nothing is received 
        prior to the expiry.
	
		$wait defaults to TRUE.    
	*/
    public function get($wait = true) 
    {
		if ($this->capacity !== null and $this->readCount >= $this->capacity || ! $this->open)
			return null;
		
		$value = null;
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
                $this->_synchronised(function() use (&$value, &$read) {
                    if (apcu_exists($this->key)) { 
                        $values = apcu_fetch($this->key); 
                        if (count($values) > 0) {
                            $value = array_shift($values); 
                            if ($value != self::CHAN_SIG_CLOSE) {
                                apcu_store($this->key, $values); // re-insert the shortened array.
                            }
                            $read = true;
                            $this->readCount++;
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
            $value = null;
            $this->open = false;
        }
        
        return $value;
    }
	
	// Alias for Channel::get().
	public function next($wait = true)
	{
		return $this->get($wait);
	}
}