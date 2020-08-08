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

use sqonk\phext\detach\Dispatcher as dispatch;
use sqonk\phext\detach\BufferedChannel;

/*
    Generate 100 seperate tasks, each sleeping for a random period of time
    before pushing the number passed to it, back out to the channel.

    This examples demonstrates that even with a high number of threads, 
    the system should reliably output the same number of results.
*/

$input = range(1, 100);

// generate seperate tasks, all of which return a number.
$chan = new BufferedChannel;
$chan->capacity(count($input)); // we'll be waiting on a maximum of 10 inputs.

$cb = function($i, $chan) {
    println('run', $i);
    usleep(rand(100, 1000));
    
    $chan->put($i);
};


dispatch::map($input, $cb)->block(false)->params($chan)->start();

// wait for all tasks to complete and then print each result.
$tally = 0;	
while ($r = $chan->get()) {
    $tally++;
    println("##RESULT: $r", 'tally', $tally);
}