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
use sqonk\phext\core\arrays;

class BufferedChannel
{
	protected $commID; // unique key for data storage of the channel. 
	protected $capacity;
	protected $readCount = 0;
    
    static private $storeLoc;
				
	public function __construct()
	{
        if (! self::$storeLoc) {
            if (! self::$storeLoc = sys_get_temp_dir())
                self::$storeLoc = __DIR__.'/.tmp';
        }
		$this->commID = 'BCHAN-'.uniqid(true);
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
	protected $wcount = 0;
    /* 
		Queue a value onto the channel, causing all readers to wake up.
	*/
    public function set($value) 
    {
        /*
            Here we prevent race conditions where another task/process
            starts reading from the file just after file_put_contents
            has created it but before anything has actually been written.
        
            To do so we output to a private file and rename it once complete.
        */
        $final = sprintf("%s/%s-%s", self::$storeLoc, $this->commID, uniqid());
        $atomic = sprintf("%s/%s", self::$storeLoc, uniqid());
        
        while (true) {
            if (file_put_contents($atomic, serialize($value), LOCK_EX) !== false) {
                if (rename($atomic, $final))
                    break;
                println('failed rename', $atomic, $final);
            }
            else
                println('failed write');
            
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
	
		If $removeAfterRead is TRUE then the value will be removed from the
		queue at the same time it is read.
	
		If the read capacity of the channel is set and has been exceeded then
		this method will return FALSE immediately.
    
        If $wait is given as an integer of 1 or more then it is used as a timeout
        in seconds. In such a case, if nothing is received before the timeout then 
        a value of TASK_CHANNEL_NO_DATA will be returned if nothing is received 
        prior to the expiry.
	
		$wait and $removeAfterRead default to TRUE.
    
        $waitTimeout defaults to 0 (never expire).
	*/
    public function get($wait = true, bool $removeAfterRead = true) 
    {
		if ($this->capacity !== null and $this->readCount >= $this->capacity)
			return false;
		
		$value = TASK_CHANNEL_NO_DATA;
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
            
            $files = glob(self::$storeLoc."/{$this->commID}*");
            foreach ($files as $queued)
            {
                if ($value = @file_get_contents($queued))
                {
                    $value = unserialize($value);
                    println('value read, count', $this->readCount+1);
                }
                if ($removeAfterRead)
					unlink($queued);    
               
				$this->readCount++; println('read count', $this->readCount);
                
                if ($value) 
                    goto done;
            }
			if (! $wait)
				break;
			
			usleep(TASK_WAIT_TIME); 
		}
        
        done:
        return $value;
    }
	
	// Alias for Channel::get().
	public function next($wait = true, bool $removeAfterRead = true)
	{
		return $this->get($wait, $removeAfterRead);
	}
}