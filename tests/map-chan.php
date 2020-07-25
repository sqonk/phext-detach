<?php
require '../vendor/autoload.php';

use sqonk\phext\detach\Dispatcher as dispatch;
use sqonk\phext\detach\BufferedChannel;

$input = range(1, 100);

// generate seperate tasks, all of which return a number.
$chan = new BufferedChannel;
$chan->capacity(count($input)); // we'll be waiting on a maximum of 10 inputs.

$cb = function($i, $chan) {
    println('run', $i);
    usleep(rand(100, 1000));
    
    $chan->put($i);
};


dispatch::map($input, $cb)->block(false)->limit(3)->params($chan)->start();

// wait for all tasks to complete and then print each result.	
$tally = 0;
while ($r = $chan->get() and $r != TASK_CHANNEL_NO_DATA) {
    $tally++;
    println("##RESULT: $r", 'tally', $tally);
}

println('expected:', count($input), 'got:', $tally, '-- pass:', (bool)(count($input) == $tally));		