<?php
declare(strict_types=1);
/**
*
* Threading
* 
* @package		phext
* @subpackage	detach
* @version		1
* 
* @license		MIT see license.txt
* @copyright	2019 Sqonk Pty Ltd.
*
*
* This file is distributed
* on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
* express or implied. See the License for the specific language governing
* permissions and limitations under the License.
*/

use PHPUnit\Framework\TestCase;
use sqonk\phext\detach\TaskMap;

class TaskMapTest extends TestCase
{   
    protected function runMap(int $amount, bool $block, ?int $limit)
    {
        $range = range(1, $amount);
        $map = new TaskMap($range, fn($i) => $i);
        $map->block($block);
        $map->limit($limit);
        
        if ($block) {
            $results = $map->start();
        }
        else
        {
            if ($limit > 0 || $limit === null) {
                $chan = $map->start();
                $results = [];
                while (($r = $chan->next()) != CHAN_CLOSED)
                    $results[] = $r;
            }
            else {
                $tasks = $map->start(); // returned value is array of tasks
                $results = detach_wait($tasks);
            }
        }
            
        $this->assertSame(expected:count($range), actual:count($results));
        foreach ($results as $r) 
        {
            $this->assertContains($r, $range);
            $range = array_filter($range, fn($v) => $v != $r);
        }
        
        detach_kill(); 
    }
    
    public function testNproc()
    {
        $nproc = detach_nproc();
        $this->assertSame(true, is_int($nproc));
        $this->assertGreaterThan(0, $nproc);
    }
    
    /**
     * @medium
     */
    public function testBlockingNoLimitWith10()
    {
        $this->runMap(10, true, 0);
    }
    
    /**
     * @medium
     */
    public function testBlockingDefaultLimitWith10()
    {
        $this->runMap(10, true, null);
    }
    
    /**
     * @medium
     */
    public function testBlockingNoLimitWith100()
    {
        $this->runMap(100, true, 0);
    }
    
    /**
     * @medium
     */
    public function testBlockingLimit3With10Tasks()
    {
        $this->runMap(10, true, 3);
    }
    
    /**
     * @medium
     */
    public function testBlockingLimit3With100Tasks()
    {
        $this->runMap(100, true, 3);
    }
    
    /**
     * @medium
     */
    public function testNonBlockingNoLimitWith10Tasks()
    {
        $this->runMap(10, false, 0);
    }
    
    /**
     * @medium
     */
    public function testNonBlockingDefaultLimitWith10Tasks()
    {
        $this->runMap(10, false, null);
    }
    
    /**
     * @medium
     */
    public function testNonBlockingNoLimitWith100Tasks()
    {
        $this->runMap(100, false, 0);
    }
    
    /**
     * @medium
     */
    public function testNonBlockingLimit3With10Tasks()
    {
        $this->runMap(10, false, 3);
    }
}