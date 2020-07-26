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
    
    static private function cleanup($tasks)
    {
        foreach ($tasks as $i => $task)
        {
            if ($task->complete() and ! $task->unread()) 
                $tasks[$i] = null;
        }
        return arrays::compact($tasks);
    }
    
    // Begin the task map.
    public function start()
    {
        if ($this->limit > 0)
        {
            if ($this->block)
            {
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
                        while (true)
                        {
                            usleep(TASK_WAIT_TIME);
                            foreach ($tasks as $i => $t) {
                                if (! in_array($t->pid, $pids) and $t->complete()) {
                                    $results[] = $t->result(); 
                                    $pids[] = $t->pid;
                                    $tasks[$i] = null;
                                    goto onedone;
                                }
                            }    
                        }  
                        
                        onedone: 
                        $tasks = arrays::compact($tasks);
                    }            
                }
            
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
                        else if (! in_array($t->pid, $pids)) {
                            $results[] = $t->result();
                            $pids[] = $t->pid;
                        }
                    }
                }
                
                return $results;
            }
            
            /*$pool = Dispatcher::detach(function()
            { 
                $results = $this->block ? [] : null;
                $tasks = [];
                foreach ($this->data as $i => $item)
                {
                    $params = [$item];
                    if ($this->params)
                        $params = array_merge($params, $this->params);
                    
                    $tasks[] = Dispatcher::detach($this->callback, $params);  
                    
                    $cnt = count($tasks);
                    if ($cnt >= $this->limit) {
                        $r = Dispatcher::wait_any($tasks);  
                        //if ($results)
                            $results[] = $r;
                        var_dump($results);
                        $tasks = self::cleanup($tasks); 
                    } 
                }
                if (is_array($results))
                {
                    foreach (Dispatcher::wait($tasks) as $r)
                        $results[] = $r;
                    return $results;
                }
            }); 
            if ($this->block) {
                $r = Dispatcher::wait($pool); println($r);
                return $r;
            }   */
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