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

define('CHAN_CLOSED', '__CHANCLOSED__');

/**
 * Execute the provided callback on a seperate process.
 * 
 * Each call creates a Task, which is a spawned
 * subprocess that operates independently of the original process.
 * 
 * It is useful for environnments that need to run a block of code
 * in parallel.
 * 
 * -- parameters:
 * @param $callback The method to be called from the detached task.
 * @param $data Any parameters to be passed to the callback method.
 * 
 * @return The newly created and started task.
 */
function detach(callable $callback, array $args = []): \sqonk\phext\detach\Task
{
    return \sqonk\phext\detach\Dispatcher::detach($callback, $args);
}

/**
 * Wait for one or more currently running tasks to complete.
 * 
 * This method will accept a single task or an array of tasks. If
 * nothing is passed in then it will wait for all currently
 * running tasks to finish.
 * 
 * Returns the result of the task or an array of results depending
 * on how many tasks are being waited on.
 */
function detach_wait($tasks = null)
{
    return \sqonk\phext\detach\Dispatcher::wait($tasks);
}

/**
 * Returns the PID of the current process the caller is on. This is
 * set to NULL for the parent process.
 */
function detach_pid()
{
    return \sqonk\phext\detach\Task::currentPID();
}

/**
 * Immediately stop all running tasks.
 */
function detach_kill(): void
{
    \sqonk\phext\detach\Dispatcher::kill();
}