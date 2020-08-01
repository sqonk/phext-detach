<?php
require '../vendor/autoload.php';

/*
    A demonstration of a buffered channel that continues to 
    pass values and then eventually closes it, freeing up the
    main task.

    The example is an overly simplified gambling table where
    a gambler starts off with a set amount and continues to 
    place bets with winnings of 2 * the bet amount and 
    winning ratio of 20%.

    Once the cash reaches 0 the gambler goes bust.
*/

use sqonk\phext\detach\BufferedChannel;

function bet($cashLeft, $chan)
{
    $odds = 2.0;
    while ($cashLeft > 0)
    {
        $bet = rand(1, $cashLeft);
        $cashLeft -= $bet;
        if (rand(1, 100) < 21) {
            $winnings = ($bet * $odds);
            $cashLeft += $winnings;
            $chan->put([$bet, $winnings, $cashLeft]);
        }
        else {
            $chan->put([$bet, 0, $cashLeft]);
        }
    }
    
    // No more values to be sent, close thc channel up, freeing up the parent
    // which is currently blocked while waiting for more data.
    $chan->close();
}

function main()
{
    $chan = new BufferedChannel;
    detach ('bet', [30, $chan]);
    
    println('The gambler starts of with 30 at the table.');
    
    while ($r = $chan->next()) 
    {
        [$bet, $winnings, $cashLeft] = $r;
        $response = ($winnings > 0) ? " and won $winnings!" : '';
        println("The gambler bet $bet{$response}. They now have $cashLeft");
    }
    
    println('The gambler lost their fortune for the glory and went bust.');
}

main();