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

use sqonk\phext\detach\Channel;

/*
    Runs on sub-process 1.
    Take the integer passed into it, increment, then output the value to the channel which sub-process 2 is waiting on.
*/
function addOne($out, $i)
{
  println('adding 1');
  $i++;
  $out->set($i);
}

/*
  Runs on sub-process 2.
  - Takes an input channel and an output channel.
  - Waits for data on the input channel (provided by sub-process 1).
  - Once received, multiplies the result by 10 then outputs the result to the second channel, which the main process is waiting on.
*/
function mul10($in, $out)
{
  $i = $in->get();
  println('multiplying 10');
  $i *= 10;
  $out->set($i);
}

$chan1 = new Channel;
$chan2 = new Channel;

// Spin up both tasks.
detach('addOne', [$chan1, 9]);
detach('mul10', [$chan1, $chan2]);

// wait for the final result that is output to the second channel, then print it.
println($chan2->get());
// will output 100.

detach_wait(); // allow external tasks to complete and shutdown correctly.
