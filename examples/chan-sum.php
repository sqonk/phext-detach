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
use sqonk\phext\core\arrays;

/*
    Split an array in 2 and sum both parts each on a seperate task, pushing
    the result to a channel that feeds back into the main program.
*/

function sum($s, $out)
{
  $out->put(array_sum($s));
}

function main()
{
  $s = [7, 2 , 8, -9, 4, 0];
    
  $c = new Channel;
  detach('sum', [array_slice($s, 0, count($s) / 2), $c]);
  detach('sum', [array_slice($s, count($s) / 2), $c]);
    
  [$x, $y] = [$c->get(), $c->get()];
  println($x, $y, $x+$y);
}

main();
