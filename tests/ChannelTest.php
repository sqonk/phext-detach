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
use sqonk\phext\detach\Channel;


class ChannelTest extends TestCase
{
    public function testBasicTransit()
    {
        $gen = function($chan, $input)
        {
            foreach ($input as $i) {
                $chan->put($i);
            }
            $chan->close();
        };

        
        $input = range(1, 10);
        $chan = new Channel;
        detach ($gen, [$chan, $input]);

        while (($r = $chan->next(2)) !== CHAN_CLOSED) { 
            $this->assertSame($r, array_shift($input));
        }
        
        detach_kill();
    }
    
    public function testWait()
    {
        $func = function($chan) {
            sleep(1);
            $chan->put(3);
        };
        $chan = new Channel;
        
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
        $chan = new Channel;
        
        detach($func, [$chan]);
        
        $this->assertSame(null, $chan->get(1));
        
        detach_kill();
    }
    
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

        detach_kill(); // clean up and remove all subprocesses ready for next test.
    }

    public function testChaining()
    {
        // Send the sequence 2, 3, 4, ... to channel 'ch'.
        $generate = function($ch)
        {
            for ($i = 2; ; $i++) {
                $ch->put($i);
            }
        };

        // Copy the values from channel 'in' to channel 'out',
        // removing those divisible by 'prime'.
        $filter = function($in, $out, $prime)
        {
            while (true) {
                $i = $in->next();
                if (($i % $prime) != 0) {
                    $out->put($i);
                }
            }
        };
        
        $expected = [2,3,5,7,11,13,17,19,23,29];

        // The prime sieve: Daisy-chain Filter processes.
        $ch = new Channel; // Create a new channel.
        detach ($generate, [$ch]); // Launch Generate routine.
        foreach (range(0, 9) as $i) 
        {
            $prime = $ch->next();
            $this->assertSame($prime, array_shift($expected));
            
            $ch1 = new Channel;
            detach ($filter, [$ch, $ch1, $prime]);
            $ch = $ch1;
        }
        
        detach_kill(); // clean up and remove all subprocesses ready for next test.
    }
    
    public function testSum()
    {
        $sum = function($s, $out) { 
            $out->put(array_sum($s));
        };

        $s = [7, 2 , 8, -9, 4, 0];

        $c = new Channel;
        detach($sum, [array_slice($s, 0, count($s) / 2), $c]);
        detach($sum, [array_slice($s, count($s) / 2), $c]);

        [$x, $y] = [$c->get(), $c->get()];
        
        $this->assertSame(17, $x);
        $this->assertSame(-5, $y);
        $this->assertSame(12, $x + $y);
        
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
        $chan = new Channel;
        detach($cannon, [$chan]);
        
        while (($r = $chan->next()) != CHAN_CLOSED)
            $this->assertSame($r, array_shift($expected));
        
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
        $chan = new Channel;
        
        detach ($func, [$in, $chan]);
        
        // wait time of 1 is here merely to ensure custom wait times flow through.
        foreach ($chan->incoming(1) as $i => $v) {
            $this->assertSame($out[$i], $v);
        }
        
        $chan = new Channel;
        detach ($func, [$in, $chan]);
        foreach ($chan as $i => $v) {
            $this->assertSame($out[$i], $v);
        }
        
        detach_kill();
    }
}