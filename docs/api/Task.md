###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > Task
------
### Task
This class is a modernised and rewritten version of the Thread class originally written by Tudor Barbu <miau@motane.lu>. It forks a seperate process (based on the parent) and executes the requested callback.

You will not need to access this class directly unless you wish to extend the class and manage the execution yourself. Instance creation is exposed through the Dispatcher and the public methods `detach()` and `detach_wait()`.
#### Methods
- [currentPID](#currentpid)
- [__construct](#__construct)
- [setRunnable](#setrunnable)
- [runnable](#runnable)
- [pid](#pid)
- [setPID](#setpid)
- [isAlive](#isalive)
- [complete](#complete)
- [result](#result)
- [unread](#unread)
- [start](#start)
- [stop](#stop)

------
##### currentPID
```php
static public function currentPID() : string
```
No documentation available.


------
##### __construct
```php
public function __construct(callable $callback = null) 
```
Create a new Task.

This merely creates the object. To schedule it for execution you must call `start()` on it.


------
##### setRunnable
```php
public function setRunnable(callable $callback) : void
```
Get or set the callback for the child process to run.


------
##### runnable
```php
public function runnable() : callable
```
Get the current callback method. This may either be a callable or a string depending upon what you have previously set.


------
##### pid
```php
public function pid() : int
```
Returns the process id (pid) of the child process.


------
##### setPID
```php
public function setPID(int $pid) : void
```
Set the PID of the task. This will be different for the parent and child processes.


------
##### isAlive
```php
public function isAlive() : bool
```
Checks if the child process is alive.


------
##### complete
```php
public function complete() : bool
```
A task has completed when it was started but is no longer alive.


------
##### result
```php
public function result() : mixed
```
Obtains the result from the child process.


------
##### unread
```php
public function unread() : bool
```
Do we have result data waiting in the pipe that has not been read in by the parent?


------
##### start
```php
public function start(array $args = []) : void
```
Start the task on a spawned child process, being a clone of the parent.

- **list<mixed>** $args The parameters to pass to the task's callback when it is executed on the child process.


------
##### stop
```php
public function stop(int $signal = SIGKILL, bool $wait = false) : void
```
Attempts to stop the child process. Returns true on success and false otherwise.

- **$signal** - SIGKILL/SIGTERM
- **$wait** - whether or not to block while the process exits.


------
