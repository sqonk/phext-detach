<?php
/**
*
* Threading
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

require '../vendor/autoload.php';


use sqonk\phext\detach\Task;

/*
    A demonstration of declaring your own subclassed Task and running
    it in an object orientated.
*/

class MyTask extends Task
{
  public function run(...$arguments)
  {
  	// ... your custom task code here.

    return 2; // Your task can return anything that can be serialised (or nothing). 
  }
}

$t = new MyTask;
$t->start();
while (! $t->complete())
	usleep(TASK_WAIT_TIME);
println('task complete, result is', $t->result());
// will print out the data returned from the task, which is '2' in this example.