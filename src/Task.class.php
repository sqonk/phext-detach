<?php
namespace sqonk\phext\detach;

/**
*
* Task
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
    This class is a modernised and rewritten version of the Thread class originally written
    by Tudor Barbu <miau@motane.lu>. It forks a seperate process (based on the parent) and 
	executes the requested callback.

	This class originally used sockets to communicate data back and fourth, which 
	evenutally proved too unreliable with various race conditions eventuating around
	process termination.

	It now makes use of file-based storage for transfering data.

	You will not need to access this class directly unless you wish to extend the class and 
	manage the execution yourself. Instance creation is exposed through the Dispatcher and the 
	public methods detach() and detach_wait().
*/
define('TASK_WAIT_TIME', 500);

class Task 
{
    protected $callback; // callback method run from the seperate process.
    protected $pid; // holds the child process id in the parent.
	protected $isParent = true; // Used internally to determine which address space we are currently in.
	protected $commID; // uniqid key for the parent and child to send data to one another.
	protected $started = false; // has the task actually begun.
	protected $resultHasBeenRetrieved = false; // helps prevent auto-cleanup on completed tasks with un-fetched results.
	
	static protected $allowParentCleanup = true;
    
    static private $storeLoc;
    
    public function __construct($callback = null) 
	{
        if (! self::$storeLoc) {
            if (! self::$storeLoc = sys_get_temp_dir())
                self::$storeLoc = __DIR__.'/.tmp';
        }
        
    	if ($callback !== null)
        	$this->setRunnable($callback);
		
		$this->commID = 'TASKID_'.uniqid(true);
    }
	
	public function __destruct()
	{
		$this->cleanup();
        if ($this->pid)
            $this->stop();
	}
	
	// clean up any communication channels associated with the process.
	public function cleanup()
	{
		if ($this->isParent and ! self::$allowParentCleanup)
			return;
				
		$suffix = $this->isParent ? 'parent' : 'child';
		$key = "{$this->commID}_{$suffix}";
		
		$path = sprintf("%s/%s", self::$storeLoc, $key);
		if (file_exists($path))
			unlink($path);
		
	}
	
	// Get or set the callback for the child process to run.
	public function setRunnable(callable $callback = null)
	{
		if ($callback) {
			if (is_callable($callback)) {
				$this->callback = $callback;
				return $this;
			}
			throw new \Exception('You must specify a valid function name that can be called from the current scope.');
		}
	}
	
	// Get the current callback method. This may either be a callable or a string
	// depending upon what you have previously set.
	public function runnable()
	{
		return $this->callback;
	}
    
    // Returns the process id (pid) of the child process.
    public function pid() 
	{
        return $this->pid;
    }
    
    // Checks if the child process is alive.
    public function isAlive() 
	{
        return pcntl_waitpid($this->pid, $status, WNOHANG) === 0;
    }
	
	// A task has completed when it was started but is no longer alive.
	public function complete()
	{
		return $this->started and ! $this->isAlive();
	}
	
	// Obtains the result from the child process.    
	public function result()
	{
		$this->resultHasBeenRetrieved = true;
		return $this->readFromChild();
	}
	
	// Do we have result data waiting in the pipe that has not been read in by the parent?
	public function unread()
	{
		$path = sprintf("%s/%s", self::$storeLoc, "{$this->commID}_parent");
		return ! $this->resultHasBeenRetrieved and file_exists($path) and filesize($path) > 0;
	}

    // Starts the child process, all the parameters are passed to the callback function.
    public function start(array $args = []) 
	{	
		$this->started = true; // flag the task has having begun.
        $pid = @pcntl_fork();
        if ($pid == -1) 
            throw new \Exception('pcntl_fork() returned a status of -1. No new process was created');
        		
        if ($pid) 
		{
            // parent process receives the pid.
            $this->pid = $pid;
        }
        else 
		{
			// child process entry point.
			$this->isParent = false;
			
			/*
				Because the forking process duplicates the current runtime we not
				only end up with the child process but also ghost copies of the 
				parent. Therefore we need a global flag that instructs the cleanup
				manager on the child-side to never wipe the communication channel for 
				the real parent operating in the originating process.
			*/
			self::$allowParentCleanup = false;
			
            // child
            pcntl_signal(SIGTERM, array($this, 'signalHandler'));
            
			if ($resp = $this->run(...$args)) 
				$this->sendToParent($resp);
			$this->cleanup();
            exit;
        }
    }
	
	// Send data to the desired process (parent or child).
	protected function write($suffix, $data)
	{
		$data = serialize($data);
		$key = "{$this->commID}_{$suffix}";
		
		if (file_put_contents(sprintf("%s/%s", self::$storeLoc, $key), $data, LOCK_EX) === false)
			throw new \RuntimeException("Failed to write to file store for '$key'");
	}
	
	// Called from child.
	protected function sendToParent($data)
	{
		$this->write('parent', $data);
	}
	
	
	// Called from parent.
	protected function sendToChild($data)
	{
		$this->write('child', $data);
	}

	// Read data from the desired process (parent or child).
	protected function read(string $suffix)
	{
		$path = sprintf("%s/%s", self::$storeLoc, "{$this->commID}_{$suffix}");
		return file_exists($path) ? unserialize(file_get_contents($path)) : '';
	}
	
	// called from parent.
	protected function readFromChild()
	{
		return $this->read('parent');
	}
	
	// called from child.
	protected function readFromParent()
	{
		return $this->read('child');
	}
	 
	// Main entry point of the child process.
	protected function run(...$arguments)
	{
        $resp = null;
        try {
            if (is_string($this->callback))
            {
                if (! empty($arguments)) 
                    $resp = call_user_func_array($this->callback, $arguments);
            
                else 
                    $resp = call_user_func($this->callback);
            }
            else 
                $resp = ($this->callback)(...$arguments);
        }
        catch (\Throwable $err) {
            println($err->getMessage());
            println($err->getTraceAsString());
        }
        
		return $resp;
	}
    
    /*
      Attempts to stop the child process. Returns true on success and false otherwise.
     
      @param integer $signal - SIGKILL/SIGTERM
      @param boolean $wait - whether or not to block while the process exits.
    */
    public function stop($signal = SIGKILL, $wait = false) 
	{
        if ($this->isAlive()) 
		{
            posix_kill($this->pid, $signal);
            if ($wait) {
                pcntl_waitpid($this->pid, $status = 0);
            }
        }
    }
    
    // signal handler
    protected function signalHandler($signal) 
	{
        // currently does nothing.
    }
}
