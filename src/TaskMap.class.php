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
    
    // Begin the task map.
    public function start()
    {
        if ($this->limit > 0)
        {
            $chan = $this->block ? new BufferedChannel : null;
            return Dispatcher::detach(function() use ($chan)
            {
                $tasks = [];
                foreach ($this->data as $item)
                {
                    $params = is_array($item) ? $item : [$item];
                    if ($this->params)
                        $params = array_merge($params, $this->params);
                    
                    $tasks[] = Dispatcher::detach($this->callback, $params);  
                    if ($chan) {
                        $chan->put(Dispatcher::wait($tasks));
                    }  
                    
                    if (count($tasks) >= $this->limit)   
                        Dispatcher::wait_any($tasks);        
                }
                
                if ($chan)
                    return $chan->get();
            }); 
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