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

define('TASK_WAIT_TIME', 500);

/**
 * This class is a modernised and rewritten version of the Thread class originally written
 * by Tudor Barbu <miau@motane.lu>. It forks a seperate process (based on the parent) and
 * executes the requested callback.
 * 
 * You will not need to access this class directly unless you wish to extend the class and
 * manage the execution yourself. Instance creation is exposed through the Dispatcher and the
 * public methods `detach()` and `detach_wait()`.
 */
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
    
    static private $envPassed = false;
    
    static private function _checkRequirements()
    {
        if (! function_exists('pcntl_fork'))
            throw new \RuntimeException("Detach requires the PCNTL extension to be installed and active.");
        if (! function_exists('apcu_fetch'))
            throw new \RuntimeException("Detach requires the APCu extension to be installed and active.");
        if (php_sapi_name() == 'cli' && ini_get('apc.enable_cli') != 1)
            throw new \RuntimeException("Detach requires the APCu be enabled for CLI usage (add apc.enable_cli to your php.ini).");
    
        self::$envPassed = true;
    }
    
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
	
    /**
     * Create a new Task. 
     * 
     * This merely creates the object. To schedule it for execution
     * you must call `start()` on it.
     */    
    public function __construct($callback = null) 
	{
    	if ($callback !== null)
        	$this->setRunnable($callback);
        
        self::rootPID(); // ensure the root PID is set before we spawn.
        
        $this->uuid = uniqid(true);
        
        $child = $this->key(self::pCHILD);
        $parent = $this->key(self::pPARENT);
        foreach ([$parent, $child, "$parent.lock", "$child.lock"] as $key)
            if (apcu_exists($key)) 
                apcu_delete($key);
    }

    protected function key($suffix): string
    {
        return "TASKID-{$this->pid}-{$this->uuid}_$suffix";
    }
	
	/**
	 * Get or set the callback for the child process to run.
	 */
	public function setRunnable(callable $callback): void
	{
		$this->callback = $callback;
	}
	
	/**
	 * Get the current callback method. This may either be a callable 
	 * or a string depending upon what you have previously set.
	 */
	public function runnable(): callable
	{
		return $this->callback;
	}
    
    /**
     * Returns the process id (pid) of the child process.
     */
    public function pid(): string
	{
        return $this->pid;
    }
    
    /**
     * Checks if the child process is alive.
     */
    public function isAlive(): bool
	{
        return pcntl_waitpid($this->pid, $status, WNOHANG) === 0;
    }
	
	/**
	 * A task has completed when it was started but is no longer alive.
	 */
	public function complete(): bool
	{
		return $this->started and ! $this->isAlive();
	}
	
	/**
	 * Obtains the result from the child process.  
	 */  
	public function result()
	{
		return $this->readFromChild();
	}
	
	/**
	 * Do we have result data waiting in the pipe that has not been read in by the parent?
	 */
	public function unread()
	{
		return apcu_exists($this->key(self::pPARENT));
	}

    /**
     * Start the task on a spwaned child process, being a clone of the parent.
     * 
     * -- parameters:
     * @param $args The parameters to pass to the task's callback when it is executed on the child process.
     */
    public function start(array $args = []): void
	{	
        if (! self::$envPassed)
            self::_checkRequirements();
        
		$this->started = true; // flag the task has having begun.
        $pid = @pcntl_fork();
        if ($pid == -1) 
            throw new \Exception('pcntl_fork() returned a status of -1. No new process was created');
        		
        if ($pid) 
		{
            // parent process receives the pid.
            $this->pid = $pid;
            $key = $this->key(self::pPARENT);
        }
        else 
		{
			// child process entry point.
			$this->isParent = false;	
            $this->pid = getmypid();
            Dispatcher::_clear();
            
            self::$currentPID = $this->pid;		
			
            // child
            pcntl_signal(SIGTERM, array($this, 'signalHandler'));
            
            register_shutdown_function(function() {
                $key = $this->key(self::pCHILD);
                if (apcu_exists($key))
                    apcu_delete($key); // remove any store data destined for the child.
                detach_kill();
            });
            
            $r = $this->run(...$args); //println('send to parent');
			$this->sendToParent($r);
			
            exit;
        }
    }
    
    protected function _synchronised($suffix, $callback): void
    {
        $lock = $this->key($suffix).".lock";
        $pid = self::$currentPID; 
        while (apcu_fetch($lock) != $pid)
        {  
            if (! apcu_add($lock, $pid))
                usleep(TASK_WAIT_TIME);
        }
        
        $callback();
        
        apcu_delete($lock);
    }
	
	// Send data to the desired process (parent or child).
	protected function write($suffix, $data): void
	{
        $written = false;
        $key = $this->key($suffix);
        while (! $written)
        {
            $this->_synchronised($suffix, function() use ($suffix, &$written, $data, $key) {
                if (apcu_add($key, $data, 0))
                    $written = apcu_exists($key);
            });
            if (! $written)
                usleep(TASK_WAIT_TIME);
        }
	}
	
	// Called from child.
	protected function sendToParent($data): void
	{
		$this->write(self::pPARENT, $data);
	}
	
	
	// Called from parent.
	protected function sendToChild($data): void
	{
		$this->write(self::pCHILD, $data);
	}

	// Read data from the desired process (parent or child).
	protected function read($suffix)
	{
		$key = $this->key($suffix);
        $value = '';
        
        $read = false;
        while (! $read)
        {
            if (apcu_exists($key))
            {
                $ok = true;
                $this->_synchronised($suffix, function() use (&$value, &$read, $key, &$ok) { 
                    if (apcu_exists($key)) { 
                        $value = apcu_fetch($key, $ok); 
                        if ($ok) { 
                            apcu_delete($key);
                            $read = true;
                        }
                    }
                });
                if (! $ok)
                    throw new \RuntimeException("Failed to read task store for '$key'");
            }
            
            if (! $read)
                usleep(TASK_WAIT_TIME); 
        }
        
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
    
    /**
     * Attempts to stop the child process. Returns true on success and false otherwise.
     * 
     * -- parameters:
     * @param $signal - SIGKILL/SIGTERM
     * @param $wait - whether or not to block while the process exits.
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
    
    protected function signalHandler($signal) 
	{
        // currently does nothing.
    }
}
