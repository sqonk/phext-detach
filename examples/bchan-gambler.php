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
    A demonstration of a buffered channel that continues to
    pass values and then eventually closes it, freeing up the
    main task.

    The example is an overly simplified gambling table where
    a gambler starts off with a set amount and continues to
    place bets with winnings of 2 x the bet amount and a
    winning ratio of 20%.

    Once the cash reaches 0 the gambler goes bust.
*/

use sqonk\phext\detach\BufferedChannel;
use sqonk\phext\core\numbers;

function bet($cashLeft, $chan)
{
  $odds = 2.0;
  while ($cashLeft > 0) {
    $bet = rand(1, $cashLeft);
    $cashLeft -= $bet;
    $quadrant = rand(1, 5);
    if (numbers::is_within(rand(1, 100), $quadrant-1 * 20, $quadrant * 20)) {
      $winnings = ($bet * $odds);
      $cashLeft += $winnings;
      $chan->put([$bet, $winnings, $cashLeft]);
    } else {
      $chan->put([$bet, 0, $cashLeft]);
    }
  }
    
  // No more values to be sent, close thc channel up, freeing up the parent
  // which is currently blocked while waiting for more data.
  $chan->close();
}

function main()
{
  $chan = new BufferedChannel;
  detach('bet', [30, $chan]);
    
  println('The gambler starts of with 30 at the table.');
    
  while (($r = $chan->next()) !== CHAN_CLOSED) {
    [$bet, $winnings, $cashLeft] = $r;
    $response = ($winnings > 0) ? " and won $winnings!" : '';
    println("The gambler bet $bet{$response}. They now have $cashLeft");
  }
    
  println('The gambler lost their fortune for the glory and went bust.');
}

main();
