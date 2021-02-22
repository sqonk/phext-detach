###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > global_functions
------
### global_functions
#### Methods
[detach](#detach)
[detach_map](#detach_map)
[detach_wait](#detach_wait)
[detach_pid](#detach_pid)
[detach_kill](#detach_kill)
[detach_nproc](#detach_nproc)

------
##### detach
```php
function detach(callable $callback, array $args = []) : sqonk\phext\detach\Task
```
Execute the provided callback on a seperate process. This method is an alias for `Dispatcher::detach`.

Each call creates a Task, which is a spawned subprocess that operates independently of the original process.

It is useful for environnments that need to run a block of code in parallel.

- **$callback** The method to be called from the detached task.
- **$data** Any parameters to be passed to the callback method.

**Returns:**  The newly created and started task.


------
##### detach_map
```php
function detach_map(iterable $data, callable $callback, array $params = null, bool $block = true, int $limit = null) 
```
Map an array of items to be processed each on a seperate task. The receiving callback function should take at least one parameter. This method is an alias for `Dispatcher::map`.

This method creates a new task map and immediately starts it.

- **$data** The array of items to be spread over seperate tasks.
- **$callback** The callback method that will receive each item on the seperate task.
- **$params** An optional array of additional [constant] parameters that will be passed to the callback.
- **$block** Whether the main program will block execution until all tasks have completed.
- **$limit** Set the maximum number of tasks that may run concurrently. 0 = unlimited. Defaults to the number of phsyical CPU cores on the running system.

**Returns:**  array|BufferedChannel The result changes based on the configuration of the task map. 
**See:**  TaskMap class for more options. 
**See:**  TaskMap::start() for information on what is returned.


------
##### detach_wait
```php
function detach_wait($tasks = null) 
```
Wait for one or more currently running tasks to complete. This method is an alias for `Dispatcher::wait`.

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
function detach_kill() : void
```
Immediately stop all running tasks. This method is an alias for `Dispatcher::kill`.


------
##### detach_nproc
```php
function detach_nproc() : int
```
Return the number of phsyical CPU cores present on the running system.


------
