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

/*
    The TaskMap class maps an array of elements each unto their own
    seperate task.
*/

class TaskMap
{
    protected $limit = 0;
    protected $params;
    protected $block = true;
    
    protected $data;
    protected $callback;
    
    public function __construct(array $data, callable $callback)
    {
        $this->data = $data;
        $this->callback = $callback;
    }
    
    /*
        Set whether the main program will block execution until all tasks have completed.
    
        The default is TRUE.
    */
    public function block(bool $waitForCompletion)
    {
        $this->block = $waitForCompletion;
        return $this;
    }
    
    /*
        A provide a series of auxiliary parameters that are provided to the callback
        in addition to the main element passed in.
    */
    public function params(...$args)
    {
        $this->params = $args;
        return $this;
    }
    
    /*
        Set the maximum number of tasks that may run concurrently. If the number is below
        1 then no limit is applied and as many tasks as there are elements in the data array
        will be created spawned.
    
        The default is 0.
    */
    public function limit(int $limit)
    {
        $this->limit = $limit;
        return $this;
    }

    protected function _runPool()
    {
        Dispatcher::_clear(); // clear out all the ghosts.
        $tasks = [];
        $results = []; $pids = [];
        foreach ($this->data as $item)
        {
            $params = is_array($item) ? $item : [$item];
            if ($this->params)
                $params = array_merge($params, $this->params);
            
            $tasks[] = Dispatcher::detach($this->callback, $params); 
            if (count($tasks) >= $this->limit) 
            {
                // The concurrency pool is already at the desired limit,
                // so wait for any of the currently running tasks to complete.
                while (true)
                {
                    usleep(TASK_WAIT_TIME);
                    foreach ($tasks as $i => $t) {
                        if (! in_array($t->pid(), $pids) and $t->complete()) {
                            $results[] = $t->result(); 
                            $pids[] = $t->pid();
                            $tasks[$i] = null;
                            goto onedone;
                        }
                    }    
                }  
                
                onedone: 
                $tasks = arrays::compact($tasks);
            }            
        }
        
        // wait for all tasks to complete.
        $notdone = true;
        while ($notdone)
        {
            usleep(TASK_WAIT_TIME);
            $notdone = false;
            foreach ($tasks as $t) {
                if (! $t->complete()) {
                    $notdone = true;
                    break;
                }
                else if (! in_array($t->pid(), $pids)) {
                    $results[] = $t->result();
                    $pids[] = $t->pid();
                }
            }
        }
        
        foreach ($threads as $t) {
            if ($t->isAlive())
                $t->stop(SIGKILL, true);
        }
        
        return $results;
    }
    
    // Begin the task map.
    public function start()
    {
        if ($this->limit > 0)
        {
            if ($this->block)
            {
                return $this->_runPool();
            }
            else
            {
                Dispatcher::detach(function() {
                    return $this->_runPool();
                });
            }
        }
        else
        {
            $tasks = [];
            foreach ($this->data as $item)
            {
                $params = is_array($item) ? $item : [$item];
                if ($this->params)
                    $params = array_merge($params, $this->params);
            
                $tasks[] = Dispatcher::detach($this->callback, $params);               
            }
            
            if ($this->block)
                return Dispatcher::wait($tasks);
        }
    }
}