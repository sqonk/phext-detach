# Example Code



- [Basic Task Creation](#basic-task-creation)
- [Waiting for tasks to complete](#Waiting-for-tasks-to-complete)
- [Automatically mapping a dataset onto seperate tasks](#Automatically-mapping-a-dataset-onto-seperate-tasks)
- [Efficient use of resources](#Efficient-use-of-resources)
- [Sharing Data](#sharing-data)
- [Waiting on more than one Channel](#Waiting-on-more-than-one-Channel)
- [Manual Creation of a Task](#Manual-Creation-of-a-Task)



### Basic task creation

Two tasks printing to output at the same time. The main script loops and prints a '.' until the sub-task is complete.

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

 

------

### Waiting for tasks to complete

Pausing the main script to wait for one or more tasks can be done with `detach_wait` . 

The following example spawns 5 seperate tasks and then demonstrates how to wait for a subset of the tasks before continuing. It then waits for the final task to complete.

``` php
function sleepAndPrint(int $amount) {
    sleep($amount);
    println('waited for ', $amount, 'secs');
}

function main() {
    detach (callback:'sleepAndPrint', args:[6]);
    
    $tasks[] = detach (callback:'sleepAndPrint', args:[4]);
    $tasks[] = detach (callback:'sleepAndPrint', args:[3]);
    $tasks[] = detach (callback:'sleepAndPrint', args:[2]);
    $tasks[] = detach (callback:'sleepAndPrint', args:[1]);

    println('waiting for 4 tasks..');
    detach_wait(tasks:$tasks);

    println('done, now waiting for last one..');
    // Pass in nothing to wait for all running tasks to complete.
    detach_wait(); 
}


main();

```

------

### Automatically mapping a dataset onto seperate tasks

A `TaskMap` allows you to finely control the distribution of a set of data onto one or more tasks for processing.

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

------

### Efficient use of resources

By default a `TaskMap` will spawn as many tasks as there are items in the data array (starting with V1.1 this has changed). If your dataset contains more than a small number of items, and the work being done on each item is relatively minimal, it is more efficient to limit the number of running tasks to a smaller number and have the TaskMap queue the distribution of the elements to each task for processing as they become free.

In V1.1 onwards TaskMaps try to detect the number of CPU cores available and defaults the pool limit to that, leaving the programmer to voluntarily re-enable unlimited task spawning when they see the need.

This example limits the number of tasks to 3 in *non-blocking* mode, which receives the results via a buffered channel.

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

foreach ($channel->incoming() as $r)
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



Here is the same example as above but using the `Dispatcher::map` interface and PHP 8's named parameters. 

Note that when calling with this method that the TaskMap is automatically started prior to returning from the call.

```php
use sqonk\phext\detach\Dispatcher as dispatch;

$numbers = range(1, 10);

// When non-blocking and running a limited pool, we 
// receive a BufferedChannel that will receive results as each task completes.
$channel = dispatch::map(data:$numbers, limit:3, block:false, callback:function($i) {
  usleep(rand(100, 1000));
  return [$i, $i ** 2];
});

foreach ($channel->incoming() as $r)
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

------

### Sharing Data

Because each task is a forked process and not a traditional thread, they do not share memory and variables between one-another. Instead they share data by communicating.

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

------

### Waiting on more than one Channel

Waiting for incoming data on multiple channels can be achieved via the [channel_select](docs/api/global_functions.md#channel_select) method.

In this simple example 2 seperate tasks emit random numbers over a single channel until one of them hits 10. The first one to do so will close their channel and cause the parent task to exit its loop. 

```php
use sqonk\phext\detach\Channel;

function cannon(string $name, Channel $out): void {
   while (true) {
      $num = rand(1, 10);
      $out->put([$name, $num]);
      usleep($num * 100);
      if ($num == 10)
          break;
   }
   $out->close();
}

$redChan = new Channel;
$blueChan = new Channel;

detach ('cannon', ['red', $redChan]);
detach ('cannon', ['blue', $blueChan]);

while (true) {
   [$value, $selected] = channel_select($redChan, $blueChan);

   if ($value == CHAN_CLOSED) {
       $name = $selected == $redChan ? 'red' : 'blue';
       println($name, 'broke the loop');
       break;
   }

   println(...$value);
}
```



------

### Manual Creation of a Task

If you wish to go the object-orientated route you can extend the Task class and manage the execution yourself.

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

