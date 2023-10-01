###### PHEXT > [Detach](../README.md) > [API Reference](index.md) > WaitGroup
------
### WaitGroup
A WaitGroup provides an alternative mechanism for synchronising the completion of a subset of tasks.

Each task should take a reference to the group and call the method `done()` upon completion.

Like `detach_wait($myTasks),` calling `wait()` on the group will block the current process until all members of the group have completed.

In addition to this a WaitGroup can also be probed manually without blocking, allowing you to control the flow of your program in a more fine grained manner.
#### Methods
- [__construct](#__construct)
- [done](#done)
- [complete](#complete)
- [wait](#wait)

------
##### __construct
```php
public function __construct(int $size) 
```
Create a new WaitGroup of the given size.

- **int** $size
The amount of times `done()` must be called upon the group before it is flagged as complete.


------
##### done
```php
public function done() : void
```
Mark the current task as complete. Each task may only call this method once on any single group.


------
##### complete
```php
public function complete() : bool
```
A group is considered complete when `done()` has been called at least as many times the size of the group (set at the point of the creation).

**Returns:**  bool `TRUE` if the WaitGroup is considered to be complete, `FALSE` if not.


------
##### wait
```php
public function wait() : void
```
Block the current task until all tasks that are part of this group have signalled their completion by calling `done()`.


------
