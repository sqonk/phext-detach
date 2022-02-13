###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > TaskMap
------
### TaskMap
The TaskMap class maps an array of elements each unto their own seperate task.
#### Methods
- [__construct](#__construct)
- [block](#block)
- [params](#params)
- [limit](#limit)
- [start](#start)

------
##### __construct
```php
public function __construct(array $data, callable $callback) 
```
Construct a new map with the provided array of items for distribution to a seperate task(s).

- **$data** The array of items to distribution across the seperate running tasks.
- **$callback** The callback method that will receive each item in $data when executed.


------
##### block
```php
public function block(bool $waitForCompletion) : sqonk\phext\detach\TaskMap
```
Set whether the main program will block execution until all tasks have completed.

The default is `TRUE`.


------
##### params
```php
public function params($args) : sqonk\phext\detach\TaskMap
```
A provide a series of auxiliary parameters that are provided to the callback in addition to the main element passed in.


------
##### limit
```php
public function limit(int $limit) : sqonk\phext\detach\TaskMap
```
Set the maximum number of tasks that may run concurrently. If the number is below 1 then no limit is applied and as many tasks as there are elements in the data array will be created spawned. NOTE that setting it to unlimited may have a detrimental affect on the performance of the code and the underlying system it is being run on.


------
##### start
```php
public function start() 
```
Begin the task map.

Depending on how you have configured the map it will return the following:


- When no pool limit and in blocking mode: An array of all data returned from each task.
- When no pool limit and in non-blocking mode: An array of spawned tasks.
- When a pool limit is set and in blocking mode: An array of all data returned from each task.
- When a pool limit is set and in non-blocking mode: A BufferedChannel that will receive the data returned from each task. The channel will automatically close when all items given to the map have been processed.


------
