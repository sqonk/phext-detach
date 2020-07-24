<?php
require '../vendor/autoload.php';

// generate 10 seperate tasks, all of which return a number.
$input = range(1, 100);

foreach ($input as $i)
  detach (function() use ($i) {
    usleep(rand(1000, 100000));
  	return [$i, $i+5];
  });

// wait for all tasks to complete and then print each result.	
$tally = 0;
foreach (detach_wait() as [$i, $num]) {
    $tally++;
    println("$i number is $num");
}
	

println('expected:', count($input), 'got:', $tally, '-- pass:', (bool)(count($input) == $tally));