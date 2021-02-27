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
use sqonk\phext\detach\{BufferedChannel,Dispatcher,TaskMap};

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
        
        $map = new TaskMap($input, $cb);
        $map->block(false)->limit(3)->params($chan)->start();

        // wait for all tasks to complete and then print each result.	
        $tally = 0;
        while (($r = $chan->get()) != CHAN_CLOSED) {
            $tally++;
        }
        
        $this->assertSame(count($input), $tally);
        
        detach_kill();
    }
    
    public function testWait()
    {
        $func = function($chan) {
            sleep(1);
            $chan->put(3);
        };
        $chan = new BufferedChannel;
        
        detach($func, [$chan]);
        
        $this->assertSame(3, $chan->get(2));
        
        detach_kill();
    }
    
    public function testWaitTimeout()
    {
        $func = function($chan) {
            sleep(2);
            $chan->put(3);
        };
        $chan = new BufferedChannel;
        
        detach($func, [$chan]);
        
        $this->assertSame(null, $chan->get(1));
        
        detach_kill();
    }
    
    public function testClose()
    {
        $cannon = function($chan) {
            foreach (range(1, 5) as $i)
                $chan->put($i);
            $chan->close();
        };
        
        $expected = range(1, 5);
        $chan = new BufferedChannel;
        detach($cannon, [$chan]);
        
        while (($r = $chan->next()) != CHAN_CLOSED)
            $this->assertSame($r, array_shift($expected));
        
        detach_kill();
    }
    
    public function testBulkSet()
    {
        $inputs = [1,2,3];
        $chan = new BufferedChannel;
        detach(function($chan, $inputs) {
            $chan->bulk_set($inputs);
            $chan->close();
        }, [$chan, $inputs]);
        
        while (($r = $chan->get()) !== CHAN_CLOSED)
            $this->assertSame($r, array_shift($inputs));
    }
    
    public function testGetAll()
    {
        $inputs = range(1,9);
        $chan = new BufferedChannel;
        
        detach(function($chan, $inputs) {
            foreach ($inputs as $v)
                $chan->put($v);
            $chan->close();
        }, [$chan, $inputs]);
        
        detach_wait();
        $results = $chan->get_all();
        $this->assertSame($inputs, $results);
        
        detach_kill();
    }
    
    public function testGenerator()
    {
        $func = function($values, $out) { 
            foreach ($values as $v)
                $out->put($v ** 2);
            $out->close();
        };
        $in = [1,2,3,4];
        $out = [1,4,9,16];
        $chan = new BufferedChannel;
        
        detach ($func, [$in, $chan]);
        
        foreach ($chan->incoming() as $i => $v) {
            $this->assertSame($out[$i], $v);
        }
    }
}