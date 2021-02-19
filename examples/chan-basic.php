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
    Demonstration of basic channel usage. Push 10 numbers through the channel
    in a seperate task and have them printed out in main program.
*/

function gen($chan)
{
    foreach (range(1, 10) as $i) {
        println("in $i");
        $chan->put($i);
    }
    $chan->close();
}

$input = range(1, 10);

$chan = new Channel;
detach ('gen', [$chan]);

while (($r = $chan->next()) !== CHAN_CLOSED) { 
    println("out $r");
}