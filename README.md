# PHEXT Detach

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.3-8892BF.svg)](https://php.net/)
[![License](https://sqonk.com/opensource/license.svg)](license.txt)

Detach is a libary for running tasks inside of a PHP script in parallel using forked processes.

This class is a modernised and rewritten version of the Thread class originally written by Tudor Barbu. It forks a seperate process (based on the parent) and executes the requested callback.

While the spawned tasks have the ability to return data back to the parent there is also the option of using Channels, a loose implentation of channels from the go language, which provide a simple way of allowing independant processes to send and receive data between one another.

Channels can also be used as logic gates for controlling the execution of various tasks by forcing them to wait for incoming data where required.

This library requires the pcntl PHP extension to be installed and active. 

## About PHEXT

The PHEXT package is a set of libraries for PHP that aim to solve common problems with a syntax that helps to keep your code both concise and readable.

PHEXT aims to not only be useful on the web SAPI but to also provide a productivity boost to command line scripts, whether they be for automation, data analysis or general research.

## Install

Via Composer

``` bash
$ composer require sqonk/phext-detach
```

Method/Class Index
------------

- [Global Methods](#global-methods)
- [Dispatcher](#dispatcher)

Available Methods
-----------------

### Global Methods

These global methods act as convienience API to the Dispatcher and save having to import namespaces.



##### detach

```php
function detach($callback, array $args = [])
```

Execute the provided callback on a seperate process.

Each call creates a Task, which is a spawned subprocess that operates independently of the original process.

It is useful for environments that need to run a block of code in parallel.

`$callback`: The method to be called from the detached task.

`$data`: Any parameters to be passed to the callback method.

Returns the newly created and started task.



##### detach_wait

```php
function detach_wait(sqonk\phext\detach\Task $task = null)
```

Wait for one or more currently running tasks to complete.

This method will accept a single task or an array of tasks. If nothing is passed in then it will wait for all currently running tasks to finish.

Returns the result of the task or an array of results depending on how many tasks are being waited on.



### Dispatcher

Dispatcher is the primary class that deals with spawning and monitoring of subtasks.

```php
use \sqonk\phext\detach\Dispatcher;
```



##### detach

```php
static public function detach($callback, array $args = [])
```

Execute the provided callback on a seperate process.

Each call creates a Task, which is a spawned subprocess that operates independently of the original process.

It is useful for environnments that need to run a block of code in parallel.

`$callback`: The method to be called from the detached task.

`$data`: Any parameters to be passed to the callback method.

Returns the newly created and started task.



##### wait

```php
static public function wait($tasks = null)
```

Wait for one or more currently running tasks to complete.

This method will accept a single task or an array of tasks. If nothing is passed in then it will wait for all currently running tasks to finish.

Returns the result of the task or an array of results depending on how many tasks are being waited on.



##### wait_any

```php
static public function wait_any(?array $tasks = null)
```

Wait for at least one task (out of many) to complete.

If nothing is passed in then it will use the set of currently running tasks.

Returns the result of the first task in the array to finish.



### Channel

Channel is a loose implentation of channels from the go language. They provide a simple way of allowing independant processes to send and receive data between one another. 

By default reading from the channel will block and in this fashion it can be used as a logic gate for controlling the execution of various tasks by forcing them to wait for incoming data where required.

```php
use \sqonk\phext\detach\Channel;

$chan = new Channel;
```



##### capacity

```php
public function capacity(int $totalDeposits)
```

Set an arbitrary limit on the number of times data will be ready from the channel. Once the limit has been reached all subsequent reads will return FALSE.

Every time this method is called it will reset the *read* count to 0.



##### set / put

```php
public function set($value)
```

```php
public function put($value) // alias
```

Queue a value onto the channel, causing all readers to wake up.



##### get / next

```php
public function get(bool $wait = true, bool $removeAfterRead = true) 
```

```php
public function next(bool $wait = true, bool $removeAfterRead = true) // next
```

Obtain the next value on the queue (if any). If `$wait` is `TRUE` then this method will block until a new value is received. Be aware that in this mode the method will block forever if no further values are queued from other tasks.

If `$removeAfterRead` is `TRUE` then the value will be removed from the queue at the same time it is read.

If the read capacity of the channel is set and has been exceeded then this method will return `FALSE` immediately.

`$wait` and `$removeAfterRead` default to `TRUE`.



## Examples

Basic usage with an anonymous function as a callback that returns the result to the parent task.

``` php
// generate 10 seperate tasks, all of which return a random number.
foreach (range(1, 10) as $i)
	detach (function() use ($i) {
		usleep(rand(1000, 100000));
		return [$i, rand(1, 4)];
	});

// wait for all tasks to complete and then print each result.	
foreach (detach_wait() as [$i, $rand])
	println("$i random number was $rand");	
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