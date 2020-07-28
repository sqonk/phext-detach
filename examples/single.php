<?php
require '../vendor/autoload.php';

$task = detach (function() {
    foreach (range(1, 10) as $i)
        print " $i ";
});

println('waiting');
while (! $task->complete()) {
    print '.';
}
    
println("\n", 'done');