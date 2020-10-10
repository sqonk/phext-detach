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



## Updating from V0.4

Release 0.5+ adjusts the way Dispatcher::map() works so that it automatically builds and starts the TaskMap, returning the result of `->start()` on the map object. Its parameters have also been expanded to accept the various TaskMap configuration options directly, in preparation for named parameters in PHP8.



## Updating from V0.3

Release 0.4+ is a significant update from previous versions. *It will break existing code that was built to use V0.3.*

Most notably, the class that was formerly named `Channel` has been renamed to `BufferedChannel` and a new `Channel` class has taken its place. You can read more about both classes below.

Also, later in development, the file-based data storage for transferring data between tasks was switched to APCu, now requiring the extension in addition to PCNTL. 



Documentation
------------

[API Reference](docs/api/index.md) now available.



## Examples

Basic usage. Two tasks printing to output at the same time.

```php
function printNumbers() {
   foreach (range(1, 10) as $i)
      print " $i ";
}

$task = detach ('printNumbers');

println('waiting');
while (! $task->complete()) {
  print '.';
}
println("\n", 'done');
/*
prints: (output may vary slightly depending on the hardware)
....................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................... 1 ....... 2 . 3  4 . 5 . 6 . 7  8 . 9 .... 10 .................................................................................................................................................................................................................................................................................................................
*/
```

 

Generate 10 tasks, each returning square of the number passed in.

``` php
// generate 10 seperate tasks, all of which return the square of the number passed in.
foreach (range(1, 10) as $i)
{
  detach (function() use ($i) {
    usleep(rand(100, 1000));
    return [$i, $i ** 2];
  }); 
}

// wait for all tasks to complete and then print each result.	
foreach (detach_wait() as [$i, $square])
  println("$i: square is $square");	
/*
prints:
1: square is 1
2: square is 4
3: square is 9
4: square is 16
5: square is 25
6: square is 36
7: square is 49
8: square is 64
9: square is 81
10: square is 100
*/
```



The same example but with the use of a `TaskMap`

```php
use sqonk\phext\detach\Dispatcher as dispatch;

use sqonk\phext\detach\TaskMap;

$numbers = range(1, 10);
$map = new TaskMap($numbers, function($i) {
  usleep(rand(100, 1000));
  return [$i, $i ** 2];
});

// The TaskMap by default will block until all tasks have completed.
$results = $map->start();

foreach ($results as [$i, $square])
  println("$i: square is $square");	
/*
prints:
1: square is 1
2: square is 4
3: square is 9
4: square is 16
5: square is 25
6: square is 36
7: square is 49
8: square is 64
9: square is 81
10: square is 100
*/
```



.. or a more complex version using non-blocking and a pool limit of 3.

```php
use sqonk\phext\detach\TaskMap;

$numbers = range(1, 10);
$map = new TaskMap($numbers, function($i) {
  usleep(rand(100, 1000));
  return [$i, $i ** 2];
});

// When non-blocking and running a limited pool, we 
// receive a BufferedChannel that will receive results as each task completes.
$channel = $map->limit(3)->block(false)->start();

while ($r = $channel->get())
  println("{$r[0]}: square is {$r[1]}");
/*
prints: (order of results returned will vary with non-blocking)
2: square is 4
3: square is 9
4: square is 16
1: square is 1
5: square is 25
7: square is 49
9: square is 81
8: square is 64
6: square is 36
10: square is 100
*/
```



The same example as above but using the Dispatcher interface and PHP8's named parameters.

```php
use sqonk\phext\detach\Dispatcher as dispatch;

$numbers = range(1, 10);

// When non-blocking and running a limited pool, we 
// receive a BufferedChannel that will receive results as each task completes.
$channel = dispatch::map(data:$numbers, limit:3, block:false, callback:function($i) {
  usleep(rand(100, 1000));
  return [$i, $i ** 2];
});

while ($r = $channel->get())
  println("{$r[0]}: square is {$r[1]}");
/*
prints: (order of results returned will vary with non-blocking)
2: square is 4
3: square is 9
4: square is 16
1: square is 1
5: square is 25
7: square is 49
9: square is 81
8: square is 64
6: square is 36
10: square is 100
*/
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



## Performance

If you are interested in how the library holds up compared to plain old single-threaded PHP (as well as comparisons with other languages) I have posted a [rudimentary CPU-work-based benchmark project](https://github.com/sqonk/exp-benchmark-tests) with some results.



## License

The MIT License (MIT). Please see [License File](license.txt) for more information.



## Alternatives

The basis for this library is a modernised, rewritten and extended version of the "Thread" class originally written by Tudor Barbu. 

Detach is *not* an asynchronous or event-driven IO framework. [ReactPHP](https://reactphp.org) and [Amp](https://amphp.org) both provide comprehensive solutions in this space.

If you have the ability to install PECL extensions there is a native concurrency extension for PHP 7.2+ called [Parallel](https://github.com/krakjoe/parallel) .

The [spatie/async](https://github.com/spatie/async) library provides an alternative solution that also uses PCNTL forking but with an API and structure that may be more familiar to many developers.

Finally, there is also the [Worker Pool](https://packagist.org/packages/qxsch/worker-pool) package.