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

use sqonk\phext\core\arrays;

/*
    Run 10 seperate tasks, each returning a random number
    between 1 and 4. Pass in a number from the command line
    to change the amount of tasks that are run.
*/

$amount = arrays::get($argv, 1, 10);

// generate 10 seperate tasks, all of which return a random number.
foreach (range(1, $amount) as $i)
  detach (function() use ($i) {
  	return [$i, rand(1, 4)];
  });

// wait for all tasks to complete and then print each result.	
foreach (detach_wait() as [$i, $rand])
	println("$i random number was $rand");	