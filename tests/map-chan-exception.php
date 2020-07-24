<?php
require '../vendor/autoload.php';

use sqonk\phext\detach\Dispatcher as dispatch;
use sqonk\phext\detach\Channel;

// generate 10 seperate tasks, all of which return a number.
// The fifth task will generate an artifical exception to test error fail-over
$chan = new Channel;
$chan->capacity(10); // we'll be waiting on a maximum of 10 inputs.

$cb = function($i, $chan) { println('run', $i);
    usleep(rand(1000, 100000));
    if ($i == 5)
        throw new Exception('An error occured.');
    $chan->put($i);
};
$input = range(1, 10);

dispatch::map($input, $cb)->block(false)->params($chan)->start();

array_pop($input); // remove 1 element for the sake of the task that will fail.

// wait for all tasks to complete and then print each result.	
$tally = 0;
while ($r = $chan->get(2) and $r != TASK_CHANNEL_NO_DATA) { // timeout after 2 seconds of waiting
    $tally++;
    println("result: $r");
}

println('expected:', count($input), 'got:', $tally, '-- pass:', (bool)(count($input) == $tally));		