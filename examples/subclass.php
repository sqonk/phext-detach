<?php
require '../vendor/autoload.php';

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