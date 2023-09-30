<?php
namespace sqonk\phext\detach;

/**
*
* Task
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

use sqonk\phext\core\arrays;

/**
 * The Dispatch class acts as a static interface to the various
 * classes of the detach library.
 */
class Dispatcher
{
  /**
   * @var list<Task>
   */
  private static array $threads = [];
        
  /**
   * Execute the provided callback on a seperate process.
   *
   * Each call creates a Task, which is a spawned
   * subprocess that operates independently of the original process.
   *
   * It is useful for environments that need to run a block of code
   * in parallel.
   *
   * -- parameters:
   * @param callable $callback The method to be called from the detached task.
   * @param array<mixed> $args Any parameters to be passed to the callback method.
   *
   * @return Task The newly created and started task.
   */
  public static function detach(callable $callback, array $args = []): Task
  {
    self::cleanup(); // remove any completed threads from the register.
        
    $t = new Task($callback);
    self::$threads[] = $t; // add to the internal register to prevent GC.
    $t->start($args);
    return $t;
  }
    
  /**
   * Map an array of items to be processed each on a seperate task.
   * The receiving callback function should take at least one parameter.
   *
   * This method creates a new task map and immediately starts it.
   *
   * -- parameters:
   * @param list<mixed>|BufferedChannel $data The items to distribute across the seperate tasks.
   * @param callable $callback The callback method that will receive each item on the seperate task.
   * @param ?array<mixed> $params An optional array of additional [constant] parameters that will be passed to the callback.
   * @param bool $block Whether the main program will block execution until all tasks have completed.
   * @param int $limit Set the maximum number of tasks that may run concurrently. 0 = unlimited. Defaults to the number of physical CPU cores on the running system.
   *
   * @return list<mixed>|BufferedChannel The result changes based on the configuration of the task map.
   * @see TaskMap class for more options.
   * @see TaskMap::start() for information on what is returned.
   */
  public static function map(array|BufferedChannel $data, callable $callback, ?array $params = null, bool $block = true, ?int $limit = null) : array|BufferedChannel
  {
    $map = new TaskMap($data, $callback);
    if ($params) {
      $map->params(...$params);
    }
    $map->block($block);
    if ($limit !== null) {
      $map->limit($limit);
    }
        
    return $map->start();
  }
    
  // Internal function.
  public static function _clear(): void
  {
    self::$threads = [];
  }
    
  /**
   * @internal
   */
  private static function cleanup(): void
  {
    $keys = array_keys(self::$threads);
    for ($i = 0; $i < count($keys); $i++) {
      if (self::$threads[$keys[$i]]->complete() and !self::$threads[$keys[$i]]->unread()) {
        self::$threads[$keys[$i]] = null;
      }
    }
    self::$threads = arrays::compact(self::$threads);
  }
    
  /**
   * Wait for one or more currently running tasks to complete.
   *
   * This method will accept a single task or an array of tasks. If
   * nothing is passed in then it will wait for all currently
   * running tasks to finish.
   *
   * --parameters:
   * @param Task|list<Task>|null $tasks A set of tasks to wait for completion. If NULL then wait for every running task.
   *
   * @return mixed The result of the task or an array of results depending on how many tasks are being waited on.
   */
  public static function wait(Task|array|null $tasks = null): mixed
  {
    if (!$tasks) {
      self::cleanup();
      $tasks = self::$threads;
    }
        
    if (is_array($tasks) && count($tasks) == 0) {
      return null;
    }
        
    if (is_array($tasks) && count($tasks) == 1) {
      $tasks = $tasks[0];
    }
        
    if ($tasks instanceof Task) {
      while (!$tasks->complete()) {
        usleep(TASK_WAIT_TIME);
      }
      return $tasks->result();
    }
        
    $notdone = true;
    while ($notdone) {
      usleep(TASK_WAIT_TIME);
      $notdone = false;
      foreach ($tasks as $t) {
        if (!$t->complete()) {
          $notdone = true;
          break;
        }
      }
    }
        
    return array_map(fn ($t) => $t->result(), $tasks);
  }
    
  /**
   * Wait for any one task (out of many) to complete.
   *
   * If nothing is passed in then it will use the set of currently
   * running tasks.
   *
   * --parameters:
   * @param ?list<Task> $tasks The set of tasks to consider. If NULL then consider all currently running tasks.
   *
   * @return mixed The result of the first task in the array to finish.
   */
  public static function wait_any(?array $tasks = null) : mixed
  {
    if (!$tasks) {
      self::cleanup();
      $tasks = self::$threads;
    }
        
    if (count($tasks) == 0) {
      return null;
    }
        
    while (true) {
      usleep(TASK_WAIT_TIME);
      foreach ($tasks as $t) {
        if ($t->complete()) {
          return $t->result();
        }
      }
    }
  }
    
  /**
   * Immediately stop all running tasks.
   */
  public static function kill(): void
  {
    foreach (self::$threads as $t) {
      if ($t->isAlive()) {
        $t->stop(SIGKILL, true);
      }
    }
      
    self::$threads = [];
  }
}
