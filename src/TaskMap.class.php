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
    //protected $tasks;
    
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
        // migrate the data to process over to a buffered channel that will feed the tasks.
        $chan = new BufferedChannel;
        foreach ($this->data as $item)
            $chan->put($item);
        $chan->close();
        
        $outBuffer = new BufferedChannel;
        $outBuffer->capacity(count($this->data));
        
        foreach (range(1, $this->limit) as $i)
        {
            Dispatcher::detach(function($feed, $out) {
                
                while ($item = $feed->next())
                {
                    $params = is_array($item) ? $item : [$item];
                    if ($this->params)
                        $params = array_merge($params, $this->params);
        
                    $r = ($this->callback)(...$params);
                    $out->put($r);
                }
            }, 
            [$chan, $outBuffer]);
        }
        
        if ($this->block)
        {
            $results = [];
            while ($r = $outBuffer->get())
                $results[] = $r;
            
            return $results;
        }
        
        return $outBuffer;
    }

    // Begin the task map.
    public function start()
    {
        if ($this->limit > 0)
        {
            return $this->_runPool();
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
            return $tasks;
        }
    }
}