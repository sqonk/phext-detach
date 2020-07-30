<?php
require '../vendor/autoload.php';

use sqonk\phext\detach\Channel;

function gen($chan)
{
    foreach (range(1, 10) as $i) {
        println("in $i");
        $chan->put($i);
    }
}

$input = range(1, 10);

$chan = new Channel;
detach ('gen', [$chan]);

while ($r = $chan->next(2)) { 
    println("out $r");
}