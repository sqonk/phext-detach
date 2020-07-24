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
        $id = hexdec(uniqid());
        $this->key = $id;
		$this->sem_id = sem_get(ftok(__FILE__, 'l'));
        $this->shm_id = ftok(__FILE__, 't');
        $this->sig_id = ftok(__FILE__, 's');
        
        $this->_signal(self::STATE_READ);
        // $sval = serialize(0);
//         $fh = @shmop_open($this->shm_id, 'c', 0775, strlen($sval));
//         shmop_write($fh, $sval, 0);
//         shmop_delete($fh);
//         shmop_close($fh);
	}

    protected function _signal($on)
    {
        $fh = shmop_open($this->sig_id, 'c', 0775, 1);
        shmop_write($fh, (string)$on, 0);
        shmop_close($fh);
    }
    
    protected function _state()
    {
        $fh = shmop_open($this->sig_id, 'a', 0, 0);
        $state = shmop_read($fh, 0, 0);
        shmop_close($fh);
        return $state;
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
        $sval = serialize($value);
        while (true)
        {
            if (sem_acquire($this->sem_id))
            {
                try {
                    if ($this->_state() == self::STATE_READ)
                    {
                        $fh = @shmop_open($this->shm_id, 'c', 0775, strlen($sval));
                        shmop_write($fh, $sval, 0);
                        @shmop_close($fh);
                        $this->_signal(self::STATE_WRITTEN);
                        break;
                    }
                }
                finally {
                     if (! sem_release($this->sem_id))
                        dump_stack("## WARNING: Failed to release lock for {$this->key}");
                }
                usleep(TASK_WAIT_TIME); // wait until existing as been deleted.
            }
            else
                dump_stack("## WARNING: Failed to aquire lock for {$this->key}, write failed.");
        }
        
        
        /* 
            Wait for the value to be read by something else.
        */
        $state = -1;
        while ($state !== self::STATE_READ)
        {
            if (sem_acquire($this->sem_id)) {
                 $state = $this->_state();
                 if (! sem_release($this->sem_id))
                     dump_stack("## WARNING: Failed to release lock for {$this->key}");
            }
               
            else
                println("## WARNING: Failed to aquire lock for {$this->key}, write failed.");
            
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
                    if ($this->_state() == self::STATE_WRITTEN)
                    {
                        $fh = @shmop_open($this->shm_id, 'a', 0, 0);
                        $value = unserialize(shmop_read($fh, 0, 0));
                        @shmop_close($fh);
                        $this->_signal(self::STATE_READ);
                            
                        break;
                    }
        			else if (! $wait)
        				break;
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