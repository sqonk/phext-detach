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
    static private $threads = [];
    static private $shutdownSet = false;
        
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
    static public function detach(callable $callback, array $args = []): Task
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
     * @param $data The array of items to be spread over seperate tasks.
     * @param $callback The callback method that will receive each item on the seperate task.
     * @param $params An optional array of additional [constant] parameters that will be passed to the callback. 
     * @param $block Whether the main program will block execution until all tasks have completed.
     * @param $limit Set the maximum number of tasks that may run concurrently. 0 = unlimited.
     *
     * @return array|BufferedChannel The result changes based on the configuration of the task map.
     * @see TaskMap class for more options.
     * @see TaskMap::start() for information on what is returned.
     */
    static public function map(iterable $data, callable $callback, ?array $params = null, bool $block = true, int $limit = 0)
    {
        $map = new TaskMap($data, $callback);
        if ($params)
            $map->params($params);
        $map->block($block);
        $map->limit($limit);
        
        return $map->start();
    }
    
    // Internal function.
    static public function _clear(): void
    {
        self::$threads = [];
    }
    
    // Internal function.
    static private function cleanup(): void
    {
		$keys = array_keys(self::$threads);
        for ($i = 0; $i < count($keys); $i++)
        {
            if (self::$threads[$keys[$i]]->complete() and ! self::$threads[$keys[$i]]->unread()) 
                self::$threads[$keys[$i]] = null;
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
     * @return The result of the task or an array of results depending on how many tasks are being waited on.
     */
    static public function wait($tasks = null)
    {
		if (! $tasks) {
		    self::cleanup();
            $tasks = self::$threads;
		}
        
        if (is_array($tasks) && count($tasks) == 0)
            return;
        
        if (is_array($tasks) && count($tasks) == 1)
            $tasks = $tasks[0];
		
        if ($tasks instanceof Task)
		{
            while (! $tasks->complete())
                usleep(TASK_WAIT_TIME);
			return $tasks->result();
		}		
        
        $notdone = true;
        while ($notdone)
        {
            usleep(TASK_WAIT_TIME);
            $notdone = false;
            foreach ($tasks as $t)
                if (! $t->complete()) {
                    $notdone = true;
                    break;
                }
        }
		
		return array_map(function($t) { 
            return $t->result(); 
        }, $tasks);				
    }
	
    /**
     * Wait for at least one task (out of many) to complete.
     * 
     * If nothing is passed in then it will use the set of currently
     * running tasks.
     * 
     * Returns the result of the first task in the array to finish.
     */
	static public function wait_any(?array $tasks = null)
	{
		if (! $tasks) {
		    self::cleanup();
            $tasks = self::$threads;
		}
		
		if (count($tasks) == 0)
			return;
		
        while (true)
        {
            usleep(TASK_WAIT_TIME);
            foreach ($tasks as $t)
                if ($t->complete()) 
                    return $t->result(); 
        }
	}
    
    /**
     * Immediately stop all running tasks.
     */
    static public function kill(): void
    {
        foreach (self::$threads as $t) {
            if ($t->isAlive())
                $t->stop(SIGKILL, true);
        }
        
        self::$threads = [];
    }
}




