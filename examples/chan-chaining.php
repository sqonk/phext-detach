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
    This example was taken from the Go tutorial. It generates a series 
    of prime numbers using a series of channels and tasks.
*/

use sqonk\phext\detach\Channel;

// Send the sequence 2, 3, 4, ... to channel 'ch'.
function generate($ch)
{
    for ($i = 2; ; $i++) {
        $ch->put($i);
    }
}

// Copy the values from channel 'in' to channel 'out',
// removing those divisible by 'prime'.
function filter($in, $out, $prime)
{
    while (true) {
        $i = $in->next();
        if (($i % $prime) != 0) {
            $out->put($i);
        }
    }
}

// The prime sieve: Daisy-chain Filter processes.
$ch = new Channel; // Create a new channel.
detach ('generate', [$ch]); // Launch Generate goroutine.
foreach (range(0, 9) as $i) 
{
    $prime = $ch->next();
    println($prime);
    $ch1 = new Channel;
    detach ('filter', [$ch, $ch1, $prime]);
    $ch = $ch1;
}

// Because this example spawns off many tasks that never exit we need to force the closure of them.
detach_kill();
