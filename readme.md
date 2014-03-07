NetBank PHP

Allows you to interface whatever PHP you’d like with your bank!

Important Note
-----
THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Using this script requires you to input plaintext banking details. If you do not know how to properly and safely store these details please do not use this script.
Please don't use this script on anything other than servers you fully control not ones that are shared.
Again, I don't take any responsibility or have any implied warranty with this script. Please use it safely.

Functions
-----

```php
public function __construct($clientNumber, $password);

public function retrieveAccounts();

public function getSummaryPosition();

public function getAccountTransactions($id);

public function getFutureTransactions();

public function getMyApplications();

public function initTransfer();

public function validateTransfer($fromId, $toId, $amount, $desc = "");

public function processTransfer($fromId, $toId, $amount, $desc = "");

public function quickTransfer($fromHash, $toHash, $amount, $desc = '', $allowOutside = false);

public function initBPay();

public function validateBPay($from, $billerId, $crn, $description, $amount);

public function processBPay($from, $billerId, $crn, $description, $amount);

public function parseToFloat($input);

protected function cleanOuput($output);

protected function callNetBank($req, $params = [], $ignoreErrors = false);
```

Usage
-----

Require the class and log in:
```php
require 'netbank.php';

$netbank = new NetBank('Client Number', 'Password');
```

List Accounts
-----

```php
$accounts = $netbank->retrieveAccounts();

print_r($accounts);
```

List Transactions
-----

```php
$accountHash = [ACCOUNT HASH];

$txns = $netbank->getAccountTransactions($accounts[$accountHash]->Id);

print_r($txns);
```

Do a Quick Transfer
-----

```php
$from = [ACCOUNT HASH];
$to = [ACCOUNT HASH];

$netbank->quickTransfer($from, $to, $amount, $desciption);
```