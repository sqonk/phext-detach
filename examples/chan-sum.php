<?php
require '../vendor/autoload.php';

use sqonk\phext\detach\Channel;
use sqonk\phext\core\arrays;

function sum($s, $out)
{
    $out->put(array_sum($s));
}

function main()
{
    $s = [7, 2 , 8, -9, 4, 0];
    
    $c = new Channel;
    detach('sum', [array_slice($s, 0, count($s) / 2), $c]);
    detach('sum', [array_slice($s, count($s) / 2), $c]);
    
    [$x, $y] = [$c->get(), $c->get()];
    println($x, $y, $x+$y);
}

main();