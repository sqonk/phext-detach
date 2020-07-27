<?php
declare(strict_types=1);


use PHPUnit\Framework\TestCase;
use sqonk\phext\detach\Channel;


class ChannelTest extends TestCase
{
    public function testLogicGates()
    {
        $addOne = function($out, $i) {
            $i++;
            $out->set($i);
        };
        
        $mul10 = function($in, $out) {
          $i = $in->get();
          $i *= 10;
          $out->set($i);
        };
        
        $chan1 = new Channel;
        $chan2 = new Channel;

        // Spin up both tasks.
        detach ($addOne, [$chan1, 9]);
        detach ($mul10, [$chan1, $chan2]);

        $this->assertSame(100, $chan2->get());

        detach_wait(); // allow external tasks to complete and shutdown correctly.
    }

}