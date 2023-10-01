###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > Dispatcher
------
### Dispatcher
The Dispatch class acts as a static interface to the various classes of the detach library.
#### Methods
- [detach](#detach)
- [map](#map)
- [_clear](#_clear)
- [wait](#wait)
- [wait_any](#wait_any)
- [kill](#kill)

------
##### detach
```php
static public function detach(callable $callback, array $args = []) : sqonk\phext\detach\Task
```
Execute the provided callback on a seperate process.

Each call creates a Task, which is a spawned subprocess that operates independently of the original process.

It is useful for environments that need to run a block of code in parallel.

- **callable** $callback The method to be called from the detached task.
- **array<mixed>** $args Any parameters to be passed to the callback method.

**Returns:**  Task The newly created and started task.


------
##### map
```php
static public function map(sqonk\phext\detach\BufferedChannel|array $data, callable $callback, array $params = null, bool $block = true, int $limit = null) : sqonk\phext\detach\BufferedChannel|array
```
Map an array of items to be processed each on a seperate task. The receiving callback function should take at least one parameter.

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
##### _clear
```php
static public function _clear() : void
```
No documentation available.


------
##### wait
```php
static public function wait(sqonk\phext\detach\Task|array|null $tasks = null) : mixed
```
Wait for one or more currently running tasks to complete.

This method will accept a single task or an array of tasks. If nothing is passed in then it will wait for all currently running tasks to finish.

--parameters: @param Task|list<Task>|null $tasks A set of tasks to wait for completion. If `NULL` then wait for every running task.

**Returns:**  mixed The result of the task or an array of results depending on how many tasks are being waited on.


------
##### wait_any
```php
static public function wait_any(array $tasks = null) : mixed
```
Wait for any one task (out of many) to complete.

If nothing is passed in then it will use the set of currently running tasks.

--parameters: @param ?list<Task> $tasks The set of tasks to consider. If `NULL` then consider all currently running tasks.

**Returns:**  mixed The result of the first task in the array to finish.


------
##### kill
```php
static public function kill() : void
```
Immediately stop all running tasks.


------
