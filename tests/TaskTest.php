<?php
declare(strict_types=1);


use PHPUnit\Framework\TestCase;
use sqonk\phext\detach\Dispatcher;

class TaskTest extends TestCase
{
    public function testResult()
    {
        detach (function() {
            return 100;
        });
        $r = detach_wait();
        $this->assertEquals(100, $r);
        
        detach_kill();
    }
    
    protected function dispatch($amount)
    {
        $input = range(1, $amount);

        foreach ($input as $i)
        {
            detach (function() use ($i) {
                usleep(rand(100, 1000));
            	return [$i, $i+5];
            });
        }
        
        $results = detach_wait();
        $this->assertSame(count($input), count($results));
        
        foreach ($results as $r)
            $this->assertEquals($r[0]+5, $r[1]);
        
        detach_kill();
    }
    
    public function testDispatch10Tasks()
    {
        $this->dispatch(10);
    }
    
    public function testDispatch100Tasks()
    {
        $this->dispatch(100);
    }
    
    public function testWaitAny()
    {
        detach (function() {
            return 1;
        });
        detach (function() {
            return 2;
        });
        
        $this->assertContains(Dispatcher::wait_any(), [1,2]);
        detach_kill(); // clear out the other result.
    }
    
    public function testWaitSingle()
    {
        $t = detach(function() {
            return 1;
        });
        $r = detach_wait($t);
        
        $this->assertSame(1, $r);
        
        detach_kill();
    }
    
    public function testWaitThree()
    {
        $input = range(1, 3);
        foreach ($input as $i)
        {
            detach(function($num) {
                return $num;
            }, [$i]);
        }
        
        $results = detach_wait();
        foreach ($results as $r) {
            $this->assertContains($r, $input);
            $input = array_filter($input, function($v) use ($r) {
                return $v != $r;
            });
        }
        
        detach_kill();
    }
    
    public function testDetachWithArgsWhatWePutInIsWhatWeGetOut()
    {
        detach (function($a, $b) {
            return [$a, $b]; // return what got given.
        }, [10, 2.5]);
        
        $r = detach_wait();  
        
        $this->assertEquals(10, $r[0]);
        $this->assertEquals(2.5, $r[1]);
        
        detach_kill();
    }
}