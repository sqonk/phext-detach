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
 * The TaskMap class maps an array of elements each unto their own
 * seperate task.
 */
class TaskMap
{
    protected int $limit;
    
    /** 
     * @var ?list<mixed> $params; 
     */
    protected ?array $params = null;
    
    protected bool $block = true;
    
    /** 
     * @var array<mixed> 
     */
    protected array $data;
    
    /** 
     * @var callable $callback 
     */
    protected $callback;
    
    /**
     * Construct a new map with the provided array of items for distribution 
     * to a seperate task(s).
     *
     * -- parameters:
     * @param $data The array of items to distribution across the seperate running tasks.
     * @param $callback The callback method that will receive each item in $data when executed.
     */
    public function __construct(array $data, callable $callback)
    {
        $this->data = $data;
        $this->callback = $callback;
        
        // default the pool limit to the number of cores on the computer.
        $this->limit = detach_nproc();
    }
    
    /**
     * Set whether the main program will block execution until all tasks have completed.
     * 
     * The default is TRUE.
     */
    public function block(bool $waitForCompletion): self
    {
        $this->block = $waitForCompletion;
        return $this;
    }
    
    /**
     * Provide a series of auxiliary parameters that are provided to the callback
     * in addition to the main element passed in.
     * 
     * @param list<mixed> ...$args
     */
    public function params(mixed ...$args): self
    {
        $this->params = $args;
        return $this;
    }
    
    /**
     * Set the maximum number of tasks that may run concurrently. If the number is below
     * 1 then no limit is applied and as many tasks as there are elements in the data array
     * will be created spawned. NOTE that setting it to unlimited may have a detrimental affect
     * on the performance of the code and the underlying system it is being run on. 
     */
    public function limit(?int $limit): self
    {
        $this->limit = $limit ?? detach_nproc();
        return $this;
    }
    
    protected function _runPool(): mixed
    {
        // migrate the data to process over to a buffered channel that will feed the tasks.
        $chan = new BufferedChannel;
        $chan->bulk_set($this->data)->close();
        
        $outBuffer = new BufferedChannel;
        $outBuffer->capacity(count($this->data));
        
        $tasks = [];
        foreach (range(1, $this->limit) as $i)
        { 
            $tasks[] = Dispatcher::detach(function($feed, $out) {
                
                while (($item = $feed->next()) !== CHAN_CLOSED)
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
            detach_wait($tasks);
            return $outBuffer->get_all(false);
        }
        
        return $outBuffer;
    }

    /**
     * Begin the task map.
     * 
     * Depending on how you have configured the map it will return the following:
     *
     * [md-block]
     * - When no pool limit and in blocking mode:* An array of all data returned from each task.
     * - When no pool limit and in non-blocking mode:* An array of spawned tasks.
     * - When a pool limit is set and in blocking mode:* An array of all data returned from each task.
     * - When a pool limit is set and in non-blocking mode:* A BufferedChannel that will receive the data returned from each task. The channel will automatically close when all items given to the map have been processed.
     * 
     * @return list<mixed>|BufferedChannel 
     */
    public function start(): array|BufferedChannel
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