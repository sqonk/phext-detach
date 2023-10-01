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
use sqonk\phext\detach\WaitGroup;

class WaitGroupTest extends TestCase
{
  /**
   * @small
   */
  public function testWait(): void 
  {
    $input = range(1, 10);
    $wg = new WaitGroup(count($input));
    $this->assertSame(expected:false, actual:$wg->complete());
    
    foreach ($input as $i) {
      detach (function ($num) use ($wg) {
        sleep(2);
        $wg->done();
        return $num;
      }, [$i]);
    }
    
    $this->assertSame(expected:false, actual:$wg->complete());
    $wg->wait();
    $this->assertSame(expected:true, actual:$wg->complete());
        
    detach_kill();
  }
}