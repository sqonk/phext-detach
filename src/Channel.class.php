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
    Channel is a loose implentation of channels from the go language. It provides a simple
    way of allowing independant processes to send and receive data between one another. 

	By default reading from the channel will block and in this fashion it can be used
	as a logic gate for controlling the execution of various tasks by forcing them to 
	wait for incoming data where required.
*/

define('TASK_CHANNEL_NO_DATA', '__data_none__');

class Channel
{
    const STATE_READ = '0';
    const STATE_WRITTEN = '1';
    
	public function __construct()
	{
        $this->key = "CHANID-".uniqid();
        $this->sem_id = sem_get(ftok(__FILE__, 'l'));
	}

		
    /* 
		Pass a value into the channel. This method will block until the 
        channel is free to receive new data and again until another task
        has received the data.
	*/
    public function set($value) 
    {
        /*
            Rules:
            - Wait for storage to be deleted before writing another value.
            - Requires lock but NOT before prior value is deleted, else we'll deadlock.
            - Write out new data.
            - Release lock, then wait until some other task has read & deleted the value.
        */
        while (true)
        {
            if (sem_acquire($this->sem_id))
            {
                try {
                    if (! apcu_exists($this->key))
                    {
                        println("write $value");
                        if (! apcu_store($this->key, $value))
                            println("failed to write");
                        break;
                    }
                }
                finally {
                     if (! sem_release($this->sem_id))
                        dump_stack("## WARNING: Failed to release lock for {$this->key}");
                } println("wait write");
                
                usleep(TASK_WAIT_TIME); // wait until existing as been deleted.
            }
            else
                dump_stack("## WARNING: Failed to aquire lock for {$this->key}, write failed.");
        }
        
        // Wait for the value to be read by something else.
        while (apcu_exists($this->key)) {
            println("wait write end");
            usleep(TASK_WAIT_TIME); // wait until existing as been deleted.    
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
        a value of TASK_CHANNEL_NO_DATA will be returned.
	
		$wait defaults to TRUE.    
	*/
    public function get($wait = true) 
    {
		$value = TASK_CHANNEL_NO_DATA;
        $started = time();
        $waitTimeout = 0; 
        if (is_int($wait)) {
            $waitTimeout = $wait;
            $wait = true;
        }
        
        /*
            - Wait until data is present.
            - Aquire lock, but not before data is present.
            - Read data
            - Delete value
            - Release lock
        */
        while (true)
        {
            if ($waitTimeout > 0 and time()-$started >= $waitTimeout)
                break;
            
            if (sem_acquire($this->sem_id, ! $wait))
            {
                try {
                    if (apcu_exists($this->key))
                    {
                        $value = apcu_fetch($this->key); println("read $value");
                        apcu_delete($this->key);    
                        break;
                    }
        			else if (! $wait)
        				break;
                    println("wait read");
                }
                finally {
                    if (! sem_release($this->sem_id))
                        dump_stack("## WARNING: Failed to release lock for {$this->key}");
                }
                usleep(TASK_WAIT_TIME); // wait until existing as been deleted.
            }
            else if ($wait)
                dump_stack("## WARNING: Failed to aquire lock for {$this->key}, write failed.");

            usleep(TASK_WAIT_TIME); 
        }
        return $value;
    }
	
	// Alias for Channel::get().
	public function next($wait = true)
	{
		return $this->get($wait);
	}
}