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

/*
    A simple example that demonstrates the main thread receiving values from
    another task.

    The main thread is a human with a tennis racket and the detached task is the
    ball cannon.

    The cannon continues to fire balls at random speeds until it fires one that is
    too fast for the player to hit back (a value of 9-10), at which point it closes the channel.
*/

use sqonk\phext\detach\Channel;

function cannon($chan)
{
  $speed = 0;
  while ($speed < 9) {
    $speed = rand(1, 10);
    $chan->set($speed);
  }
    
  // No more values to be sent, close the channel up, freeing up the parent
  // which is currently blocked while waiting for more data.
  $chan->close();
}

function main()
{
  $chan = new Channel;
  detach('cannon', [$chan]);
    
  while (($r = $chan->next()) !== CHAN_CLOSED) {
    $response = ($r > 8) ? ', it was too fast and the player missed.' : 'and the player hit it back.';
    println("Cannon fired a ball at the player at speed $r $response");
  }
}

main();
