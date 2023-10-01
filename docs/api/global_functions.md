###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > global_functions
------
### global_functions
#### Methods
- [detach](#detach)
- [detach_map](#detach_map)
- [detach_wait](#detach_wait)
- [detach_pid](#detach_pid)
- [detach_kill](#detach_kill)
- [detach_nproc](#detach_nproc)
- [channel_select](#channel_select)

------
##### detach
```php
function detach(callable $callback, array $args = []) : sqonk\phext\detach\Task
```
Execute the provided callback on a seperate process. This method is an alias for `Dispatcher::detach`.

Each call creates a Task, which is a spawned subprocess that operates independently of the original process.

It is useful for environnments that need to run a block of code in parallel.

- **callable** $callback The method to be called from the detached task.
- **array<mixed>** $args Any parameters to be passed to the callback method.

**Returns:**  Task The newly created and started task.


------
##### detach_map
```php
function detach_map(sqonk\phext\detach\BufferedChannel|array $data, callable $callback, array $params = null, bool $block = true, int $limit = null) : sqonk\phext\detach\BufferedChannel|array
```
Map an array of items to be processed each on a seperate task. The receiving callback function should take at least one parameter. This method is an alias for `Dispatcher::map`.

This method creates a new task map and immediately starts it.

- **list<mixed>|BufferedChannel** $data The items to distribute across the seperate tasks.
- **callable** $callback The callback method that will receive each item on the seperate task.
- **?array<mixed>** $params An optional array of additional [constant] parameters that will be passed to the callback.
- **bool** $block Whether the main program will block execution until all tasks have completed.
- **int** $limit Set the maximum number of tasks that may run concurrently. 0 = unlimited. Defaults to the number of physical CPU cores on the running system.

**Returns:**  list<mixed>|BufferedChannel The result changes based on the configuration of the task map. 
**See:**  TaskMap class for more options. 
**See:**  TaskMap::start() for information on what is returned.


------
##### detach_wait
```php
function detach_wait(sqonk\phext\detach\Task|array|null $tasks = null) : mixed
```
Wait for one or more currently running tasks to complete. This method is an alias for `Dispatcher::wait`.

This method will accept a single task or an array of tasks. If nothing is passed in then it will wait for all currently running tasks to finish.

--parameters: @param Task|list<Task>|null $tasks A set of tasks to wait for completion. If `NULL` then wait for every running task.

**Returns:**  mixed The result of the task or an array of results depending on how many tasks are being waited on.


------
##### detach_pid
```php
function detach_pid() : string
```
Returns the PID of the current process the caller is on. This is set to `NULL` for the parent process.

**Returns:**  string The ID of the current process.


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
Return the number of physical CPU cores present on the running system.

**Returns:**  int The number of physical CPU cores present on the running system.


------
##### channel_select
```php
function channel_select(sqonk\phext\detach\Channel|sqonk\phext\detach\BufferedChannel ...$channels) : array
```
Takes a series of Channels or BufferedChannels and returns the value of the first one to receive a value.

This method will block indefinitely until it receives a non-null value from one of the provided channels. It should be noted that any channel closure will also qualify as a valid return value.

**Returns:**  array{mixed, Channel|BufferedChannel} $channels An array containing the first value received and the respective channel to have received it.


**Throws:**  InvalidArgumentException if any parameter given is not an object of type Channel or BufferedChannel.


------
