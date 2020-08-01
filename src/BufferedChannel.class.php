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
	protected $commID; // unique key for data storage of the channel. 
	protected $capacity;
	protected $readCount = 0;
    
    private const BOUNDARY = "#___CHANITEMBOUNDARY___#";
    private const CHAN_SIG_CLOSE = "#__CHAN-CLOSE__#";
    
    static private $storeLoc;
				
	public function __construct()
	{
        if (! self::$storeLoc) {
            if (! self::$storeLoc = sys_get_temp_dir())
                self::$storeLoc = __DIR__.'/.tmp';
        }
		$this->key = 'BCHAN-'.uniqid();
        $this->filepath = self::$storeLoc."/{$this->key}";
        $this->sem_id = sem_get(ftok(__FILE__, 'l'));
        
        $this->createdByPID = detach_pid();
        register_shutdown_function(function() {
            $this->cleanup();
        });
	}
    
    public function __destruct()
    {
        $this->cleanup();
    }
    
    protected function cleanup()
    {
        if ($this->sem_id === null or @sem_acquire($this->sem_id))
        {
            if (file_exists($this->filepath) and detach_pid() == $this->createdByPID)
                unlink($this->filepath);
            if ($this->sem_id && ! @sem_release($this->sem_id))
               dump_stack("## WARNING: Failed to release lock for {$this->key}");
        }
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
        $this->set(self::CHAN_SIG_CLOSE);
        $this->sem_id = null;
    }
	
    /* 
		Queue a value onto the channel, causing all readers to wake up.
	*/
    public function set($value) 
    { 
        if (! $this->sem_id)
            return;
        /*
            Rules:
            - Require lock.
            - Append new data.
            - Release lock.
        */
        while (true)
        {
            if (sem_acquire($this->sem_id))
            {
                try {
                    $fh = fopen($this->filepath, 'a');
                    flock($fh, LOCK_EX);
                    fwrite($fh, serialize($value).self::BOUNDARY);
                    
                    break;
                }
                finally {
                    if (isset($fh)) {
                        flock($fh, LOCK_UN);
                        fclose($fh);
                    }
                     if (! sem_release($this->sem_id))
                        dump_stack("## WARNING: Failed to release lock for {$this->key}");
                } 
                
                usleep(TASK_WAIT_TIME); // wait until existing as been deleted.
            }
            else
                dump_stack("## WARNING: Failed to aquire lock for {$this->key}, write failed.");
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
		if ($this->capacity !== null and $this->readCount >= $this->capacity || ! $this->sem_id)
			return null;
		
		$value = null;
        $started = time();
        $waitTimeout = 0; 
        if (is_int($wait)) {
            $waitTimeout = $wait;
            $wait = true;
        }
		
		while (true)
		{
            if ($waitTimeout > 0 and time()-$started >= $waitTimeout)
                break;
            
            if (@sem_acquire($this->sem_id))
            {
                $delete = false;
                try {
                    if (file_exists($this->filepath))
                    {
                        $size = filesize($this->filepath);
                        if ($size > 0)
                        {
                            $fh = fopen($this->filepath, 'r+');
                            flock($fh, LOCK_EX);
                            $contents = fread($fh, $size);
                            $boundpos = strpos($contents, self::BOUNDARY);
                            $value = substr($contents, 0, $boundpos);
                            $contents = substr($contents, $boundpos + strlen(self::BOUNDARY));
                        
                            $len = strlen($contents);
                            if ($len > 0)
                            {
                                rewind($fh);
                                fwrite($fh, $contents);
                                ftruncate($fh, $len);
                            }
                            else
                                $delete = true;
                        
                            $value = unserialize($value);
                            $this->readCount++; 
                            break;
                        }
                    }
                }
                finally {
                    if (isset($fh) && is_resource($fh)) {
                        flock($fh, LOCK_UN);
                        fclose($fh);
                    }
                    if ($delete)
                        unlink($this->filepath); // contents reduced to 0.
                    sem_release($this->sem_id);
                }    
            }
            
			if (! $wait)
				break;
			
			usleep(TASK_WAIT_TIME); 
		}
        
        if ($value == self::CHAN_SIG_CLOSE)
        {
            $value = null;
            @sem_remove($this->sem_id);
            $this->sem_id = null;
        }
        
        return $value;
    }
	
	// Alias for Channel::get().
	public function next($wait = true)
	{
		return $this->get($wait);
	}
}