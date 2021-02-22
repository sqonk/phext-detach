###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > Dispatcher
------
### Dispatcher
The Dispatch class acts as a static interface to the various classes of the detach library.
#### Methods
[detach](#detach)
[map](#map)
[_clear](#_clear)
[wait](#wait)
[wait_any](#wait_any)
[kill](#kill)

------
##### detach
```php
static public function detach(callable $callback, array $args = []) : sqonk\phext\detach\Task
```
Execute the provided callback on a seperate process.

Each call creates a Task, which is a spawned subprocess that operates independently of the original process.

It is useful for environnments that need to run a block of code in parallel.

- **$callback** The method to be called from the detached task.
- **$data** Any parameters to be passed to the callback method.

**Returns:**  The newly created and started task.


------
##### map
```php
static public function map(iterable $data, callable $callback, array $params = null, bool $block = true, int $limit = null) 
```
Map an array of items to be processed each on a seperate task. The receiving callback function should take at least one parameter.

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
##### _clear
```php
static public function _clear() : void
```
No documentation available.


------
##### wait
```php
static public function wait($tasks = null) 
```
Wait for one or more currently running tasks to complete.

This method will accept a single task or an array of tasks. If nothing is passed in then it will wait for all currently running tasks to finish.

**Returns:**  The result of the task or an array of results depending on how many tasks are being waited on.


------
##### wait_any
```php
static public function wait_any(array $tasks = null) 
```
Wait for at least one task (out of many) to complete.

If nothing is passed in then it will use the set of currently running tasks.

Returns the result of the first task in the array to finish.


------
##### kill
```php
static public function kill() : void
```
Immediately stop all running tasks.


------
