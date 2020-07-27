<?php
declare(strict_types=1);


use PHPUnit\Framework\TestCase;
use sqonk\phext\detach\{BufferedChannel,Dispatcher};

class BufferedChannelTest extends TestCase
{
    public function testMappedPoolRollingValues()
    {
        $input = range(1, 100);
        
        // generate seperate tasks, all of which return a number.
        $chan = new BufferedChannel;
        $chan->capacity(count($input)); // we'll be waiting on a maximum of 10 inputs.

        $cb = function($i, $chan) {
            usleep(rand(100, 1000));
    
            $chan->put($i);
        };
        
        Dispatcher::map($input, $cb)->block(false)->limit(3)->params($chan)->start();

        // wait for all tasks to complete and then print each result.	
        $tally = 0;
        while ($r = $chan->get() and $r != TASK_CHANNEL_NO_DATA) {
            $tally++;
        }
        
        $this->assertSame(count($input), $tally);
    }
}