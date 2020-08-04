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
    protected $pid; // holds the child process id.
    protected $isParent = true; // Used internally to determine which address space we are currently in.
	protected $started = false; // has the task actually begun.
    
    protected const pCHILD = 'child';
    protected const pPARENT = 'parent';
    
    static protected $currentPID = '_parent_';
    static protected $rootPID;
    
    static public function rootPID()
    {
        if (! self::$rootPID)
            self::$rootPID = getmypid();
        return self::$rootPID;
    }
    
    static public function currentPID()
    {
        return self::$currentPID;
    }
	    
    public function __construct($callback = null) 
	{
    	if ($callback !== null)
        	$this->setRunnable($callback);
        
        self::rootPID(); // ensure the root PID is set before we spawn.
    }

    protected function key($suffix)
    {
        return "TASKID-{$this->pid}_$suffix";
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
		return $this->readFromChild();
	}
	
	// Do we have result data waiting in the pipe that has not been read in by the parent?
	public function unread()
	{
		return apcu_exists($this->key(self::pPARENT));
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
            $this->pid = getmypid();
            
            self::$currentPID = $this->pid;		
			
            // child
            pcntl_signal(SIGTERM, array($this, 'signalHandler'));
            
            register_shutdown_function(function() {
                $key = $this->key(self::pCHILD);
                if (apcu_exists($key))
                    apcu_delete($key); // remove any store data destined for the child.
            });
            
			if ($resp = $this->run(...$args)) 
				$this->sendToParent($resp);
			
            exit;
        }
    }
	
	// Send data to the desired process (parent or child).
	protected function write($suffix, $data)
	{
		$data = serialize($data);
		$key = $this->key($suffix);
		
		if (! apcu_store($key, $data))
			throw new \RuntimeException("Failed to write to task store for '$key'");
	}
	
	// Called from child.
	protected function sendToParent($data)
	{
		$this->write(self::pPARENT, $data);
	}
	
	
	// Called from parent.
	protected function sendToChild($data)
	{
		$this->write(self::pCHILD, $data);
	}

	// Read data from the desired process (parent or child).
	protected function read($suffix)
	{
		$key = $this->key($suffix);
        $value = '';
		if (apcu_exists($key)) {
            $value = apcu_fetch($key, $ok);
            if (! $ok)
                throw new \RuntimeException("Failed to read task store for '$key'");
            else
                $value = unserialize($value);
            apcu_delete($key);
        }
        else
            println($key, 'does not exist');
        
        return $value;
	}
	
	// called from parent.
	protected function readFromChild()
	{
		return $this->read(self::pPARENT);
	}
	
	// called from child.
	protected function readFromParent()
	{
		return $this->read(self::pCHILD);
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
                pcntl_waitpid($this->pid, $status);
            }
        }
    }
    
    // signal handler
    protected function signalHandler($signal) 
	{
        // currently does nothing.
    }
}
