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

    This example is similar to bchan-map.php differing in that it applies
    a concurrent pool limit, allowing only a set number of tasks to run
    simulatenously.
*/
function gen($i, $chan) {
    println('run', $i);
    
    $chan->put($i);
};


$input = range(1, 100);

// generate seperate tasks, all of which return a number.
$chan = new BufferedChannel;
$chan->capacity(count($input)); // we'll be waiting on a maximum of 10 inputs.

dispatch::map($input, 'gen')->block(false)->limit(3)->params($chan)->start();

// wait for all tasks to complete and then print each result.	
$tally = 0;
while ($r = $chan->get()) {
    $tally++;
    println("##RESULT: $r", 'tally', $tally);
}

println('---done');