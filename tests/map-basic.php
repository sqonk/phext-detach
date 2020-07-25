<?php
require '../vendor/autoload.php';

use sqonk\phext\detach\Dispatcher as dispatch;

// generate 10 seperate tasks, all of which return a number.
$input = range(1, 10);
$r = dispatch::map($input, function($i) { println('run', $i);
	usleep(rand(100, 1000));
	return [$i, $i];
})->start();

// wait for all tasks to complete and then print each result.	
$tally = 0;
foreach ($r as [$i, $num]) {
    $tally++;
    println("$i number is $num");	
}
	

println('expected:', count($input), 'got:', $tally, '-- pass:', (bool)(count($input) == $tally));