###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > global_functions
------
### global_functions
#### Methods
[detach](#detach)
[detach_wait](#detach_wait)
[detach_pid](#detach_pid)
[detach_kill](#detach_kill)

------
##### detach
```php
function detach(callable $callback, array $args = []) 
```
Execute the provided callback on a seperate process.

Each call creates a Task, which is a spawned subprocess that operates independently of the original process.

It is useful for environnments that need to run a block of code in parallel.

- **$callback** The method to be called from the detached task.
- **$data** Any parameters to be passed to the callback method.

**Returns:**  The newly created and started task.


------
##### detach_wait
```php
function detach_wait($tasks = null) 
```
Wait for one or more currently running tasks to complete.

This method will accept a single task or an array of tasks. If nothing is passed in then it will wait for all currently running tasks to finish.

Returns the result of the task or an array of results depending on how many tasks are being waited on.


------
##### detach_pid
```php
function detach_pid() 
```
Returns the PID of the current process the caller is on. This is set to `NULL` for the parent process.


------
##### detach_kill
```php
function detach_kill() 
```
Immediately stop all running tasks.


------
