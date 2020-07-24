<?php
require '../vendor/autoload.php';

use sqonk\phext\detach\Dispatcher as dispatch;
use sqonk\phext\detach\BufferedChannel;

$input = range(1, 100);

// generate seperate tasks, all of which return a number.
$chan = new BufferedChannel;
$chan->capacity(count($input)); // we'll be waiting on a maximum of 10 inputs.

$cb = function($i, $chan) {
    usleep(rand(1000, 100000));
    $chan->put($i+5);
};


dispatch::map($input, $cb)->block(false)->params($chan)->start();

// wait for all tasks to complete and then print each result.	
$tally = 0;
while ($r = $chan->get() and $r != TASK_CHANNEL_NO_DATA) {
    $tally++;
    println("result: $r");
}

println('expected:', count($input), 'got:', $tally, '-- pass:', (bool)(count($input) == $tally));		