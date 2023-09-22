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

// Demonstrate a task sub-process running in parallel to the main script.

$task = detach(function () {
  foreach (range(1, 10) as $i) {
    print " $i ";
  }
});

println('waiting');
while (! $task->complete()) {
  print '.';
}

println("\n", 'done');
