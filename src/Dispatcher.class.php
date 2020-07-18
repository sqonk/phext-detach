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

class Dispatcher
{
    static private $threads = [];
    
	/*
		Execute the provided callback on a seperate process.

	    Each call creates a Task, which is a spawned
	    subprocess that operates independently of the original process.

	    It is useful for environnments that need to run a block of code
	    in parallel.

	    @param $callback    The method to be called from the detached task.
	    @param $data        Any parameters to be passed to the callback method.

	    @returns            The newly created and started task.
	*/
    static public function detach($callback, array $args = [])
    {
        self::cleanup(); // remove any completed threads from the register.
        
        $t = new Task($callback);
		self::$threads[] = $t; // add to the internal register to prevent GC.    
        $t->start($args);   
        return $t;
    }
    
    static public function map(iterable $data, callable $callback, array $options = [])
    {
        $block = arrays::safe_value($options, 'block', true);
        $constantParams = arrays::safe_value($options, 'constants', null);
        $poolLimit = arrays::safe_value($options, 'limit', 0);
        
        if ($poolLimit > 0)
        {
            $chan = $block ? new Channel : null;
            self::detach(function() use ($data, $callback, $constantParams, $poolLimit, $chan) 
            {
                $tasks = [];
                foreach ($data as $item)
                {
                    $params = is_array($item) ? $item : [$item];
                    if ($constantParams)
                        $params = array_merge($params, $constantParams);
            
                    $tasks[] = self::detach($callback, $params);    
                    if (count($tasks) >= $poolLimit)   
                        self::wait_any($tasks);        
                }
                if ($chan) {
                    $r = self::wait($tasks);
                    $chan->put($r);
                }
            });
            if ($chan)
                return $chan->get();
        }
        else
        {
            $tasks = [];
            foreach ($data as $item)
            {
                $params = is_array($item) ? $item : [$item];
                if ($constantParams)
                    $params = array_merge($params, $constantParams);
            
                $tasks[] = self::detach($callback, $params);               
            }
            
            if ($block)
                return self::wait($tasks);
        }
    }
    
    static private function cleanup()
    {
		$keys = array_keys(self::$threads);
        for ($i = 0; $i < count($keys); $i++)
        {
            if (self::$threads[$keys[$i]]->complete() and ! self::$threads[$keys[$i]]->unread()) 
                self::$threads[$keys[$i]] = null;
        }
        self::$threads = arrays::compact(self::$threads);
    }
	
    /* 
		Wait for one or more currently running tasks to complete.
		
		This method will accept a single task or an array of tasks. If 
		nothing is passed in then it will wait for all currently 
		running tasks to finish.
	
		Returns the result of the task or an array of results depending
		on how many tasks are being waited on.
	*/
    static public function wait($tasks = null)
    {
		if (! $tasks) 
			$tasks = self::$threads;
		
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
		
		return array_map(function($t) { return $t->result(); }, self::$threads);				
        
    }
	
    /* 
		Wait for at least one task (out of many) to complete.
		
		If nothing is passed in then it will use the set of currently 
		running tasks.
	
		Returns the result of the first task in the array to finish.
	*/
	static public function wait_any(?array $tasks = null)
	{
		if (! $tasks) 
			$tasks = self::$threads;
		
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
}




