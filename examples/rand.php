<?php
require '../vendor/autoload.php';

use sqonk\phext\core\arrays;

$amount = arrays::get($argv, 1, 10);

// generate 10 seperate tasks, all of which return a random number.
foreach (range(1, $amount) as $i)
  detach (function() use ($i) {
  	return [$i, rand(1, 4)];
  });

// wait for all tasks to complete and then print each result.	
foreach (detach_wait() as [$i, $rand])
	println("$i random number was $rand");	