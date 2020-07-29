<?php
require '../vendor/autoload.php';

use sqonk\phext\detach\Channel;

// Send the sequence 2, 3, 4, ... to channel 'ch'.
function generate($ch)
{
    for ($i = 2; ; $i++) {
        $ch->put($i);
    }
}

// Copy the values from channel 'in' to channel 'out',
// removing those divisible by 'prime'.
function filter($in, $out, $prime)
{
    while (true) {
        $i = $in->next();
        if (($i % $prime) != 0) {
            $out->put($i);
        }
    }
}

// The prime sieve: Daisy-chain Filter processes.
$ch = new Channel; // Create a new channel.
detach ('generate', [$ch]); // Launch Generate goroutine.
foreach (range(0, 9) as $i) 
{
    $prime = $ch->next();
    println($prime);
    $ch1 = new Channel;
    detach ('filter', [$ch, $ch1, $prime]);
    $ch = $ch1;
}

//detach_kill();