<?php
namespace sqonk\phext\detach;

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

/**
 * A WaitGroup provides an alternative mechanism for synchronising the completion of a 
 * subset of tasks. 
 * 
 * Each task should take a reference to the group and call the method done() upon completion.
 * 
 * Like detach_wait($myTasks), calling wait() on the group will block the current process until
 * all members of the group have completed.
 * 
 * In addition to this a WaitGroup can also be probed manually without blocking, allowing you to
 * control the flow of your program in a more fine grained manner.
 */
final class WaitGroup
{
  private readonly string $id;
  
  
  /**
   * Create a new WaitGroup of the given size.
   * 
   * @param int $size 
   * The amount of times done() must be called upon the group before it is flagged as complete.
   */
  public function __construct(private readonly int $size) 
  {
    $this->id = uniqid('DetachWG', true);
    
    if ($this->size < 1) {
      throw new \Exception("A WaitGroup size must be 1 or more.");
    }
    
    apcu_store($this->id, 0);
  }
  
  private function _synchronised(callable $callback): void
  {
    $lock = "{$this->id}.lock";
    $pid = detach_pid();
    while (apcu_fetch($lock) != $pid) {
      if (!apcu_add($lock, $pid)) {
        usleep(TASK_WAIT_TIME);
      }
    }

    $callback();

    apcu_delete($lock);
  }
  
  /**
   * Mark the current task as complete. Each task may only call this method once on any single group.
   */
  public function done(): void 
  {
    $this->_synchronised(function() {
      apcu_inc($this->id);
    });
  }
  
  /**
   * A group is considered complete when done() has been called at least as many times the size of 
   * the group (set at the point of the creation).
   * 
   * @return bool TRUE if grouped is completed, FALSE if not.
   */
  public function complete(): bool 
  {
    $done = 0;
    $this->_synchronised(function() use (&$done) {
      $done = apcu_fetch($this->id);
    });
    return $done >= $this->size;
  }
  
  /**
   * Block the current task until all tasks that are part of this group have signalled their
   * completion by calling done().
   */
  public function wait(): void 
  {
    while (!$this->complete()) {
      usleep(TASK_WAIT_TIME);
    }
  }
}