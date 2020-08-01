<?php
require '../vendor/autoload.php';

/*
    A simple example that demonstrates the main thread receiving values from
    another task. 

    The main thread is a human with a tennis racket and the detached task is the 
    ball cannon.

    The cannon continues to fire balls at random speeds until it fires one that is
    too fast for the player to hit back, at which point it closes the channel.
*/

use sqonk\phext\detach\Channel;

function cannon($chan)
{
    $speed = 0;
    while ($speed < 9)
    {
        $speed = rand(1, 10);
        $chan->set($speed);
    }
    
    // No more values to be sent, close thc channel up, freeing up the parent
    // which is currently blocked while waiting for more data.
    $chan->close();
}

function main()
{
    $chan = new Channel;
    detach ('cannon', [$chan]);
    
    while ($r = $chan->next()) {
        $response = ($r > 8) ? ', it was too fast and the player missed.' : 'and the player hit it back.';
        println("Cannon fired a ball at the player at speed $r $response");
    }
}

main();