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
use sqonk\phext\detach\{BufferedChannel,Channel};

class OtherTest extends TestCase
{
    public function testChannelSelect()
    {
        $fun = function(array $channels) {
            foreach (range(1, 6) as $i) {
                $ci = $i > 3 ? $i - 4 : $i - 1;
                $channels[$ci]->put($i);
            }
            $channels[0]->close();
        };
        
        $channels = [new Channel, new Channel, new BufferedChannel];
        detach ($fun, [$channels]);
        
        while (true) {
            [$val, $chan] = channel_select(...$channels);
            if ($val == CHAN_CLOSED)
                break;
            
            if (contains([1,4], $val))
                $this->assertSame($channels[0], $chan);
            else if (contains([2,5], $val))
                $this->assertSame($channels[1], $chan);
            else if (contains([3,6], $val))
                $this->assertSame($channels[2], $chan);
            else
                throw new Exception('unknown value');
        }
        
        detach_kill();
    }
}