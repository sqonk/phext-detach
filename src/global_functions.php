<?php
/**
*
* Async
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

use \sqonk\phext\detach\Task;
use \sqonk\phext\detach\TaskMap;
use \sqonk\phext\detach\Channel;
use \sqonk\phext\detach\BufferedChannel;
use \sqonk\phext\detach\Dispatcher;

define('CHAN_CLOSED', '__CHANCLOSED__');

/**
 * Execute the provided callback on a seperate process. This method is an alias for `Dispatcher::detach`.
 *
 * Each call creates a Task, which is a spawned
 * subprocess that operates independently of the original process.
 *
 * It is useful for environnments that need to run a block of code
 * in parallel.
 *
 * -- parameters:
 * @param callable $callback The method to be called from the detached task.
 * @param array<mixed> $args Any parameters to be passed to the callback method.
 *
 * @return Task The newly created and started task.
 */
function detach(callable $callback, array $args = []): Task
{
  return Dispatcher::detach($callback, $args);
}

/**
 * Map an array of items to be processed each on a seperate task. The receiving callback function should take
 * at least one parameter. This method is an alias for `Dispatcher::map`.
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
function detach_map(
  array|BufferedChannel $data, 
  callable $callback, 
  ?array $params = null, 
  bool $block = true, 
  ?int $limit = null
): array|BufferedChannel {
  return Dispatcher::map($data, $callback, $params, $block, $limit);
}

/**
 * Wait for one or more currently running tasks to complete. This method is an alias for `Dispatcher::wait`.
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
function detach_wait(Task|array|null $tasks = null): mixed
{
  return Dispatcher::wait($tasks);
}

/**
 * Returns the PID of the current process the caller is on. This is
 * set to NULL for the parent process.
 *
 * @return string The ID of the current process.
 */
function detach_pid(): string
{
  return Task::currentPID();
}

/**
 * Immediately stop all running tasks. This method is an alias for `Dispatcher::kill`.
 */
function detach_kill(): void
{
  Dispatcher::kill();
}

/**
 * Return the number of physical CPU cores present on the running system.
 *
 * @return int The number of physical CPU cores present on the running system.
 */
function detach_nproc(): int
{
  static $nproc;
    
  if ($nproc === null) {
    $os = strtolower(php_uname('s'));
    if (starts_with($os, 'win')) {
      $command = 'echo %NUMBER_OF_PROCESSORS%';
    } elseif (contains($os, 'darwin')) {
      $command = 'sysctl -n hw.physicalcpu';
    } else {
      $command = 'nproc';
    }
    $r = rtrim(shell_exec($command));
    $nproc = is_numeric($r) ? (int)$r : 2;
  }
    
  return $nproc;
}

/**
 * Takes a series of Channels or BufferedChannels and returns the value of the first one to receive a value.
 *
 * This method will block indefinitely until it receives a non-null value from one of the provided channels. It
 * should be noted that any channel closure will also qualify as a valid return value.
 *
 * @return array{mixed, Channel|BufferedChannel} $channels An array containing the first value received and the respective channel to have received it.
 *
 * @throws InvalidArgumentException if any parameter given is not an object of type Channel or BufferedChannel.
 */
function channel_select(Channel|BufferedChannel ...$channels): array
{
  while (true) {
    foreach ($channels as $ch) {
      if (($value = $ch->get(false)) !== null) {
        return [$value, $ch];
      }
    }
    usleep(TASK_WAIT_TIME);
  }
}
