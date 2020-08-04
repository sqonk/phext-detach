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
Channel is a loose implentation of channels from the Go language. It provides a simple way of allowing independant processes to send and receive data between one another. 

A channel is a block-in, and (by default) a block-out mechanism, meaning that the task that sets a value will block until another task has received it.
*/

class Channel
{    
    private const CHAN_SIG_CLOSE = "#__CHAN-CLOSE__#";
    private $open = true;
    
	public function __construct()
	{
        $this->key = "CHANID-".uniqid();
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
    
    // Close off the channel, signalling to the receiver that no further values will be sent.
    public function close()
    {
        if (! $this->open)
            return;
        
        $this->set(self::CHAN_SIG_CLOSE);
        $this->open = false;
    }
		
    /* 
		Pass a value into the channel. This method will block until the 
        channel is free to receive new data again.
	*/
    public function set($value) 
    {
        if (! $this->open)
            return;
        
        $written = false;
        while (! $written)
        {
            $this->_synchronised(function() use ($value, &$written) {
                if (apcu_add($this->key, serialize($value)))
                    $written = true;
            });
            if (! $written)
                usleep(TASK_WAIT_TIME); 
        }
        
        
        // Wait for the value to be read by something else.
        while (apcu_exists($this->key)) {
            usleep(TASK_WAIT_TIME);    
        }

        return $this;
    }
	
	// Alias for Channel::set().
	public function put($value)
	{
		$this->set($value);
		return $this;
	}

    /*
		Obtain the next value on the channel (if any). If $wait is TRUE then
		this method will block until a new value is received. Be aware that
		in this mode the method will block forever if no further values
		are sent from other tasks.
    
        If $wait is given as an integer of 1 or more then it is used as a timeout
        in seconds. In such a case, if nothing is received before the timeout then 
        a value of NULL will be returned.
	
		$wait defaults to TRUE.    
	*/
    public function get($wait = true) 
    {
        if (! $this->open)
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
            - Aquire lock, but not before data is present.
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
                        $value = unserialize(apcu_fetch($this->key));
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