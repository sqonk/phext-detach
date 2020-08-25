# PHEXT Detach

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.3-8892BF.svg)](https://php.net/)
[![License](https://sqonk.com/opensource/license.svg)](license.txt)[![Build Status](https://travis-ci.org/sqonk/phext-detach.svg?branch=master)](https://travis-ci.org/sqonk/phext-detach)

Detach is a library for running tasks inside of a PHP script in parallel using forked processes. It clones a seperate process (based on the parent) and executes the requested callback. 

It is light weight and relies on little more than the PCNTL and APCu PHP extensions plus a minor set of composer packages.

Detach is also minimalist in nature with just a small set of functions and classes to memorise. There is no event system and no architecture to learn, allowing it to fit easily within an existing code structure. 

See the [examples](#examples) for a quick start guide.

While the spawned tasks have the ability to return data back to the parent there is also the option of using Channels, a loose implementation of channels from the Go language, which provide a simple way of allowing independent processes to send and receive data between one another.

Channels can also be used as logic gates for controlling the execution of various tasks by forcing them to wait for incoming data where required.



## Install

Via Composer

``` bash
$ composer require sqonk/phext-detach
```



## Updating from V0.3

Release 0.4+ is a significant update from previous versions. *It will break existing code that was built to use V0.3.*

Most notably, the class that was formerly named `Channel` has been renamed to `BufferedChannel` and a new `Channel` class has taken its place. You can read more about both classes below.

Also, later in development, the file-based data storage for transferring data between tasks was switched to APCu, now requiring the extension in addition to PCNTL. 



Method/Class Index
------------

- [Global Methods](#global-methods)
- [Dispatcher](#dispatcher)
- [Channel](#channel)
- [BufferedChannel](#bufferedchannel)
- [TaskMap](#taskmap)
- [Examples](#examples)



Available Methods
-----------------

### Global Methods

These global methods act as convienience API to the Dispatcher and save having to import namespaces.



##### detach

```php
function detach($callback, array $args = [])
```

A global namespace interface to [Dispatcher::detach](#dispatcher::detach)



##### detach_wait

```php
function detach_wait($tasks = null)
```

A global namespace interface to [Dispatcher::wait](#dispatcher::wait)



##### detach_pid

```php
function detach_pid()
```

Returns the PID of the current process the caller is on. This is set to NULL for the parent process.



##### detach_kill

```php
function detach_kill()
```

Immediately stop all running tasks.



### Dispatcher

Dispatcher is the primary class that deals with spawning and monitoring of subtasks.

```php
use \sqonk\phext\detach\Dispatcher;
```



##### Dispatcher::detach

```php
static public function detach($callback, array $args = [])
```

Execute the provided callback on a seperate process.

Each call creates a Task, which is a spawned subprocess that operates independently of the original process.

It is useful for environnments that need to run a block of code in parallel.

`$callback`: The method to be called from the detached task.

`$data`: Any parameters to be passed to the callback method.

Returns the newly created and started task.



##### Dispatcher::map

```php
static public function map(iterable $data, callable $callback, array $options = [])
```

Map an array of items to be processed each on a seperate task.  The receiving callback function should take at least one parameter.

This method returns a TaskMap object that can be further configured. See [TaskMap](#taskmap) class for more options.



##### Dispatcher::wait

```php
static public function wait($tasks = null)
```

Wait for one or more currently running tasks to complete.

This method will accept a single task or an array of tasks. If nothing is passed in then it will wait for all currently running tasks to finish.

Returns the result of *a* task or an array of results depending on how many tasks are being waited on.



##### Dispatcher::wait_any

```php
static public function wait_any(?array $tasks = null)
```

Wait for at least one task (out of many) to complete.

If nothing is passed in then it will use the set of currently running tasks.

Returns the result of the first task to complete.



##### Dispatcher::kill

```php
static public function kill()
```

Immediately stop all running tasks.



### Channel

Channel is a loose implentation of channels from the Go language. It provides a simple way of allowing independant processes to send and receive data between one another. 

*A channel is a block-in, and (by default) a block-out mechanism*, meaning that the task that sets a value will block until another task has received it.

```php
use \sqonk\phext\detach\Channel;

$chan = new Channel;
```



##### set / put

```php
public function set($value) : Channel
```

```php
public function put($value) : Channel // alias
```

Push a new value through the channel. The calling task will block until another task has received the value.



##### get / next

```php
public function get($wait = true) : Channel
```

```php
public function next($wait = true) : Channel // next
```

Obtain the next value on the channel (if any). If `$wait` is `TRUE` then this method will block until a new value is received. Be aware that in this mode the method will block forever if no further values are sent from other tasks.

If `$wait` is given as an integer of 1 or more then it is used as a timeout  in seconds. In such a case, if nothing is received before the timeout then a value of `NULL` will be returned.

`$wait` defaults to `TRUE`. 



##### close

```php
public function close() : Channel
```

Close off the channel, signalling to the receiver that no further values will be sent.



### BufferedChannel

A BufferedChannel is a queue of values that may be passed between tasks. Unlike a standard channel, it may continue to accept new values before any existing ones have been read in via another task.

BufferedChannels are an effective bottle-necking system where data obtained from multiple tasks may need to be fed into a singular thread for post-processing.

```php
use \sqonk\phext\detach\BufferedChannel;

$chan = new BufferedChannel;
```



##### capacity

```php
public function capacity(int $totalDeposits) : BufferedChannel
```

Set an arbitrary limit on the number of times data will be written to the channel. Once the limit has been reached the channel will be closed.

Every time this method is called it will reset the internal write count to 0.



##### set / put

```php
public function set($value) : BufferedChannel
```

```php
public function put($value) : BufferedChannel // alias
```

Queue a new value onto the channel, causing all waiting readers to wake up. 

If a capacity limit is set, adding the new value was successful and the capacity was hit then the channel will be closed.



##### get / next

```php
public function get($wait = true) : mixed
```

```php
public function next($wait = true) : mixed // next
```

Obtain the next value on the channel (if any). If `$wait` is `TRUE` then this method will block until a new value is received. Be aware that in this mode the method will block forever if no further values are sent from other tasks.

If the value retrieved is a 'channel closed' signal then `NULL` will be returned. All subsequent calls will also return `NULL`.

If `$wait` is given as an integer of 1 or more then it is used as a timeout  in seconds. In such a case, if nothing is received before the timeout then a value of `NULL` will be returned.

`$wait` defaults to `TRUE`. 



##### bulk_set

```php
public function bulk_set(array $values)
```

Queue a bulk set of values onto the channel, causing all readers to wake up.

If you have a large number of items to push onto the queue at once then this method will be faster than calling set() for every element in the array.

If a capacity limit is set, adding the new values was successful and the capacity was hit then the channel will be closed.



##### get_all

```php
public function get_all($wait = true)
```

Obtain all values currently residing on the queue (if any). If `$wait` is `TRUE` then this method will block until a new value is received. Be aware that in this mode the method will block forever if no further values are queued from other tasks.

If the value retrieved is a 'channel closed' signal then `NULL` will be returned. All subsequent calls will also return `NULL`.

If `$wait` is given as an integer of 1 or more then it is used as a timeout in seconds. In such a case, if nothing is received before the timeout then a value of `NULL` will be returned if nothing is received prior to the expiry.

`$wait` defaults to `TRUE`.  



##### close

```php
public function close() : BufferedChannel
```

Close off the channel, signalling to the receiver that no further values will be sent.



### TaskMap

The TaskMap class maps an array of elements each unto their own seperate task. A TaskMap can be created by either direct class instantiation or the [Dispatcher](#dispatcher) interface.

```php
use sqonk\phext\detach\Dispatcher as dispatch;

$array = []; // ... your data array.
$map = dispatch::map($array, 'myCallBack');

```

or

```php
use sqonk\phext\detach\TaskMap;

$array = []; // ... your data array.
$map = new TaskMap($array, 'myCallBack')
```



##### block

```php
public function block(bool $waitForCompletion) : TaskMap
```

Set whether the main program will block execution until all tasks have completed. The default is TRUE.



##### params

```php
public function params(...$args) : TaskMap
```

Provide a series of auxiliary parameters that are provided to the callback in addition to the main element passed in.



##### limit

```php
public function limit(int $limit) : TaskMap
```

Set the maximum number of tasks that may run concurrently. If the number is below 1 then no limit is applied and as many tasks as there are elements in the data array will be spawned.

The default is 0 (unlimited).



##### start

```php
public function start() : mixed
```

Begin execution of the tasks.

Depending on how you have configured the map it will return the following:

- *When no pool limit and in blocking mode:* An array of all data returned from each task.
- *When no pool limit and in non-blocking mode:* An array of spawned tasks.
- *When a pool limit is set and in blocking mode:* An array of all data returned from each task.
- *When a pool limit is set and in non-blocking mode:* A BufferedChannel that will receive the data returned from each task. The channel will automatically close when all items given to the map have been processed.



## Examples

Basic usage with an anonymous function as a callback.

```php
$task = detach (function() {
    foreach (range(1, 10) as $i)
        print " $i ";
});

println('waiting');
while (! $task->complete()) {
  print '.';
}
println("\n", 'done');
```

 

Generate 10 tasks, each returning a random number to the parent process.

``` php
// generate 10 seperate tasks, all of which return a random number.
foreach (range(1, 10) as $i)
  detach (function() use ($i) {
    usleep(rand(100, 1000));
    return [$i, rand(1, 4)];
  });

// wait for all tasks to complete and then print each result.	
foreach (detach_wait() as [$i, $rand])
	println("$i random number was $rand");	
```



The same example but with the use of `Dispatcher::map`

```php
use sqonk\phext\detach\Dispatcher as dispatch;

// generate 10 seperate tasks, all of which return a random number.
$r = dispatch::map(range(1, 10), function($i) {
	usleep(rand(100, 1000));
  return [$i, rand(1, 4)];
})->start();

// wait for all tasks to complete and then print each result.	
foreach ($r as [$i, $rand])
	println("$i random number was $rand");	
```



.. or a more complex version using non-blocking and a pool limit of 3.

```php
use sqonk\phext\detach\Dispatcher as dispatch;
use sqonk\phext\detach\BufferedChannel;

function addFive($i, $chan) 
{
  usleep(rand(100, 1000));
  $chan->put([$i, $i+5]);
};

// generate 10 seperate tasks, all of which return the number passed in + 5.
$chan = new BufferedChannel;
$chan->capacity(10); // we'll be waiting on a maximum of 10 inputs.

dispatch::map(range(1, 10), 'addFive')->limit(3)->block(false)->params($chan)->start();

// wait for all tasks to complete and then print each result.	
while ($r = $chan->get(2))
	println($r[0], 'number is:', $r[1]);
```



This example illustrates the use of Channels to control flow between the parent and two sub-tasks. 

``` php
use sqonk\phext\detach\Channel;

/*
	Runs on sub-process 1. 
	Take the integer passed into it, increment, then output the value to the channel which sub-process 2 is waiting on.
*/
function addOne($out, $i) {
  println('adding 1');
  $i++;
  $out->set($i);
}

/*
  Runs on sub-process 2.
  - Takes an input channel and an output channel.
  - Waits for data on the input channel (provided by sub-process 1).
  - Once received, multiplies the result by 10 then outputs the result to the second channel, which the main process is waiting on.
*/
function mul10($in, $out) {
  $i = $in->get();
  println('multiplying 10');
  $i *= 10;
  $out->set($i);
}

$chan1 = new Channel;
$chan2 = new Channel;

// Spin up both tasks.
detach ('addOne', [$chan1, 9]);
detach ('mul10', [$chan1, $chan2]);

// wait for the final result that is output to the second channel, then print it.
println($chan2->get());
// will output 100.
```



If you wish to go the object-orientated route you can extend the Task class and manage the execuation yourself.

``` php
use sqonk\phext\detach\Task;

class MyTask extends Task
{
  public function run(...$arguments)
  {
  	// ... your custom task code here.

    return 2; // Your task can return anything that can be serialised (or nothing). 
  }
}

$t = new MyTask;
$t->start();
while (! $t->complete())
  usleep(TASK_WAIT_TIME);
println('task complete, result is', $t->result());
// will print out the data returned from the task, which is '2' in this example.
```



## Credits

Theo Howell

Please see original concept of pnctl Threading by Tudor Barbu @ <a href="https://github.com/motanelu/php-thread">motanelu/php-thread</a>



## License

The MIT License (MIT). Please see [License File](license.txt) for more information.



## Alternatives

The solution provided in this library is nothing new. It is a modernised, rewritten and extended version of the "Thread" class originally written by Tudor Barbu. 

Detach is *not* an asynchronous or event-driven IO framework. [ReactPHP](https://reactphp.org) and [Amp](https://amphp.org) both provide comprehensive solutions in this space.

If you have the ability to install PECL extensions there is a native concurrency extension for PHP 7.2+ called [Parallel](https://github.com/krakjoe/parallel) .

The [spatie/async](https://github.com/spatie/async) library provides an alternative solution that also uses PCNTL forking but with an API and structure that may be more familiar to many developers.

Finally, there is also the [Worker Pool](https://packagist.org/packages/qxsch/worker-pool) package.