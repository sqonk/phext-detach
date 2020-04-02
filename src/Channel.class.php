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
    Channel is a loose implentation of channels from the go language. They provide a simple
    way of allowing independant processes to send and receive data between one another. 

	By default reading from the channel will block and in this fashion it can be used
	as a logic gate for controlling the execution of various tasks by forcing them to 
	wait for incoming data where required.
*/
use sqonk\phext\core\arrays;

define('TASK_CHANNEL_NO_DATA', '__data_none__');

class Channel
{
	protected $commID; // unique key for data storage of the channel. 
	protected $capacity;
	protected $readCount = 0;
				
	public function __construct()
	{
		$this->commID = 'ASYNC_CHAN_ID_'.uniqid();
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
	}
	
    /* 
		Queue a value onto the channel, causing all readers to wake up.
	*/
    public function set($value) 
    {
		file_put_contents(tempnam(sys_get_temp_dir(), $this->commID), serialize($value));
    }
	
	// Alias for Channel::set().
	public function put($value)
	{
		$this->set($value);
		return $this;
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
	
		$wait and $removeAfterRead default to TRUE.
	*/
    public function get(bool $wait = true, bool $removeAfterRead = true) 
    {
		if ($this->capacity !== null and $this->readCount >= $this->capacity)
			return false;
		
		$value = TASK_CHANNEL_NO_DATA;
		
		while (true)
		{
			if ($queued = arrays::first(glob(sys_get_temp_dir()."/{$this->commID}*"))) 
			{
				$value = unserialize(@file_get_contents($queued));
				if ($removeAfterRead) 
					unlink($queued);
				$this->readCount++;
				
				break;
			}
			else if (! $wait)
				break;
			
			usleep(TASK_WAIT_TIME); 
		}

        return $value;
    }
	
	// Alias for Channel::get().
	public function next(bool $wait = true, bool $removeAfterRead = true)
	{
		return $this->get($wait, $removeAfterRead);
	}
}