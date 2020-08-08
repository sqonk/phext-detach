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
        while ($r = $chan->get()) {
            $tally++;
        }
        
        $this->assertSame(count($input), $tally);
        
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
        
        while ($r = $chan->next())
            $this->assertSame($r, array_shift($expected));
        
        detach_kill();
    }
}