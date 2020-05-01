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

## Documentation

Forthcoming, refer to class comments and functions for the interim.


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