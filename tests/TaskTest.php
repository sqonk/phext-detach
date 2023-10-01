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
use sqonk\phext\detach\Dispatcher;
use sqonk\phext\detach\Task;

class TaskTest extends TestCase
{
  /**
   * @small
   */
  public function testResult()
  {
    detach(function () {
      return 100;
    });
    $r = detach_wait();
    $this->assertEquals(expected:100, actual:$r);
        
    detach_kill();
  }
    
  /**
   * @small
   */
  public function testReturnType()
  {
    $t = detach(function () {
      sleep(1);
    });
    $this->assertSame(Task::class, get_class($t));
  }
    
  /**
   * @medium
   */
  public function testCompleteCheck()
  {
    $t = new Task(function () {
      sleep(1);
    });
    $this->assertSame(false, $t->complete());
    $t->start();
    detach_wait($t);
    $this->assertSame(true, $t->complete());
        
    detach_kill();
  }
    
  /**
   * @medium
   */
  protected function dispatch($amount)
  {
    $input = range(1, $amount);

    foreach ($input as $i) {
      detach(function () use ($i) {
        usleep(rand(100, 1000));
        return [$i, $i+5];
      });
    }
        
    $results = detach_wait();
    $this->assertSame(count($input), count($results));
        
    foreach ($results as $r) {
      $this->assertEquals($r[0]+5, $r[1]);
    }
        
    detach_kill();
  }
    
  /**
   * @medium
   */
  public function testDispatch10Tasks()
  {
    $this->dispatch(10);
  }
    
  /**
   * @medium
   */
  public function testDispatch100Tasks()
  {
    $this->dispatch(100);
  }
    
  /**
   * @small
   */
  public function testWaitAny()
  {
    detach(function () {
      return 1;
    });
    detach(function () {
      return 2;
    });
        
    $this->assertContains(Dispatcher::wait_any(), [1,2]);
    detach_kill(); // clear out the other result.
  }
    
  /**
   * @small
   */
  public function testWaitSingle()
  {
    $t = detach(function () {
      return 1;
    });
    $r = detach_wait($t);
        
    $this->assertSame(1, $r);
        
    detach_kill();
  }
    
  /**
   * @small
   */
  public function testWaitThree()
  {
    $input = range(1, 3);
    foreach ($input as $i) {
      detach(function ($num) {
        return $num;
      }, [$i]);
    }
        
    $results = detach_wait();
    foreach ($results as $r) {
      $this->assertContains($r, $input);
      $input = array_filter($input, function ($v) use ($r) {
        return $v != $r;
      });
    }
        
    detach_kill();
  }
    
  /**
   * @small
   */
  public function testDetachWithArgsWhatWePutInIsWhatWeGetOut()
  {
    detach(function ($a, $b) {
      return [$a, $b]; // return what got given.
    }, [10, 2.5]);
        
    $r = detach_wait();
        
    $this->assertEquals(10, $r[0]);
    $this->assertEquals(2.5, $r[1]);
        
    detach_kill();
  }
}
