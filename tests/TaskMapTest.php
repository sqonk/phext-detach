<?php
declare(strict_types=1);


use PHPUnit\Framework\TestCase;
use sqonk\phext\detach\Dispatcher;

class TaskMapTest extends TestCase
{   
    protected function runMap($amount, bool $block, int $limit = 0)
    {
        $range = range(1, $amount);
        $map = Dispatcher::map($range, function($i) {
        	return $i;
        });
        if ($limit)
            $map->limit($limit);
        $map->block($block);
        
        $results = $map->start();
        if (! $block)
            $results = detach_wait();
        $this->assertSame(count($range), count($results));
        foreach ($results as $r) {
            $this->assertContains($r, $range);
            $range = array_filter($range, function($v) use ($r) {
                return $v != $r;
            });
        }
            
        
        detach_kill(); 
    }
    
    public function testBlockingNoLimitWith10()
    {
        $this->runMap(10, true);
    }
    
    public function testBlockingNoLimitWith100()
    {
        $this->runMap(100, true);
    }
    
    public function testBlockingLimit3With10Tasks()
    {
        $this->runMap(10, true, 3);
    }
    
    public function testBlockingLimit3With100Tasks()
    {
        $this->runMap(100, true, 3);
    }
    
    public function testNonBlockingNoLimitWith10Tasks()
    {
        $this->runMap(10, false);
    }
    
    public function testNonBlockingNoLimitWith100Tasks()
    {
        $this->runMap(100, false);
    }
    
    public function testNonBlockingLimit3With10Tasks()
    {
        $this->runMap(10, false, 3);
    }
}