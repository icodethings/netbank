<?php

/**
 * Class NetBank
 */
class NetBank{

	/**
	 * Current Session ID used by NetBank
	 *
	 * @var null
	 */
	protected $SID = null;

	/**
	 * The first data returned from the login command
	 *
	 * @var null
	 */
	protected $initialData = null;

	/**
	 * Toggled if the initial data is stale and we should retrieve the data from NetBank if required
	 *
	 * @var bool
	 */
	protected $initialDataStale = false;

	/**
	 * Name of the cookies file
	 *
	 * @var string
	 */
	protected $cookies = 'cookies.txt';

	/**
	 * NetBank URL
	 */
	const netbankURL = 'https://www1.my.commbank.com.au';

	/**
	 * Log into NetBank
	 *
	 * @param $clientNumber
	 * @param $password
	 * @throws Exception
	 */
	public function __construct($clientNumber, $password){
		$this->initialData = $this->callNetBank(
			'login',
			['UserName' => $clientNumber, 'Password' => $password, 'Token' => '']
		);

		if(isset($this->initialData->RequiresToken) and $this->initialData->RequiresToken){
			throw new \Exception('This account requires token login.');
		}
	}

	// ========== Bank Functions ==========

	/**
	 * Return an array of accounts from the initial data or from NetBank if the data is too old.
	 *
	 * Array is in the following format:
	 *
	 * Array
	 * (
	 *    [ACCOUNT HASH] => stdClass Object
	 *        (
	 *            * Snip * (Check out the raw data for more details)
	 *            [AccountName] => [Account Name]
	 *            [AccountNumber] => [Account Number (BSB & ACC) or CC Number]
	 *            [AccountNumberHash] => [Account Hash - same as key]
	 *            [AvailableFunds] => $60.00 CR
	 *            [Balance] => $60.00 CR
	 *            [Id] => 1 (Used for getAccountTransactions())
	 *        )
	 * )
	 *
	 * This isn't a full look into what is returned as there is quite a lot of stuff that isn't very useful.
	 *
	 * @return array
	 */
	public function retrieveAccounts(){
		$accounts = [];

		if(!$this->initialDataStale){
			$data = $this->initialData;
		} else{
			$data = $this->callNetBank('getAccounts');
		}

		foreach($data->AccountGroups as $group){
			foreach($group->ListAccount as $account){
				$accounts[$account->AccountNumberHash] = $account;
			}
		}

		return $accounts;
	}

	/**
	 * Returns the current summary position of the logged in user
	 *
	 * @see parseToFloat
	 * @return mixed
	 */
	public function getSummaryPosition(){
		if(!$this->initialDataStale){
			$data = $this->initialData;
		} else{
			$data = $this->callNetBank('getAccounts');
		}

		return $data->SummaryPosition;
	}

	/**
	 * Returns an array of transactions in the following format:
	 *
	 * Array
	 * (
	 *     [0] => stdClass Object
	 *         (
	 *             [Amount] => $50.00 DR
	 *             [Balance] =>
	 *             [Description] => Transfer to xx4382 NetBank<br/>[DESCRIPTION]
	 *             [EffectiveDate] => 04/03/14
	 *             [IsPending] =>
	 *         )
	 *
	 *     [1] => stdClass Object
	 *         (
	 *             [Amount] => $50.00 CR
	 *             [Balance] => $50.00 CR
	 *             [Description] => Transfer from [NAME] CommBank app<br/>[DESCRIPTION
	 *             [EffectiveDate] => 04/03/14
	 *             [IsPending] =>
	 *         )
	 *  )
	 *
	 * Obviously you can see Balance doesn't always get returned. This happens when you have a balance of $0.
	 *
	 * ID required is returned by retrieveAccounts() function.
	 *
	 * @param $id
	 * @return array
	 */
	public function getAccountTransactions($id){
		$ret = $this->callNetBank('getTransactions', ['AccountID' => $id, 'AccountIdIsUser' => 'true']);
		$ret = $ret->Transactions;

		return $ret;
	}

	/**
	 * @return object
	 */
	public function getFutureTransactions(){
		return $this->callNetBank('getFutureTransactions');
	}

	/**
	 * @return object
	 */
	public function getMyApplications(){
		return $this->callNetBank('getMyApplications');
	}

	/**
	 * Initialises a transfer. This will return a list of AccountsFrom, AccountsToLinked and AccountsToNotLinked that
	 * have Id values that can be used for both the validateTransfer() and processTransfer() functions.
	 *
	 * Example:
	 *
	 * stdClass Object
	 * (
	 *     [AccountsFrom] => Array
	 *         (
	 *             [0] => stdClass Object
	 *                 (
	 *                     [AccountName] => [Account Name]
	 *                     [AccountNumber] => [Account Number]
	 *                     [AccountNumberHash] => [Account Number Hash]
	 *                     [AvailableFunds] => $80.00 CR
	 *                     [CardNumberHash] => [Card Number Hash]
	 *                     [Id] => 1
	 *                     [ProductSystemCode] => [ProductSystemCode]
	 *                 )
	 *         )
	 *     [AccountsToLinked] => Array
	 *         (
	 *             [0] => stdClass Object
	 *                 (
	 *                     [AccountName] => [Account Name]
	 *                     [AccountNumber] => [Account Number]
	 *                     [AccountNumberHash] => [Account Number Hash]
	 *                     [AvailableFunds] => $60.00 CR
	 *                     [CardNumberHash] => [Card Number Hash]
	 *                     [Id] => 1
	 *                 )
	 *         )
	 *     [AccountsToNotLinked] => Array
	 *        (
	 *            [0] => stdClass Object
	 *                (
	 *                    [AccountName] => [Account Name]
	 *                    [AccountNumber] => [Account Number]
	 *                    [AccountNumberHash] => [Account Number Hash]
	 *                    [AvailableFunds] => NULL
	 *                    [CardNumberHash] => [Card Number Hash]
	 *                    [Id] => 9
	 *                )
	 *         )
	 * )
	 *
	 * Comes back with 3 different arrays:
	 *
	 *  * AccountsFrom - these are all the available accounts you can transfer from
	 *  * AccountsToLinked - these are all YOUR accounts you can transfer to.
	 *  * AccountsToNotLinked - these are all the accounts that aren't yours that you have stored inside your address
	 * 	  book.
	 *
	 * Use the Id variable for validateTransfer() and processTransfer() for both to and from.
	 *
	 * If you don't want to go though this check out quickTransfer()
	 *
	 * @see quickTransfer()
	 * @throws Exception
	 * @return object
	 */
	public function initTransfer(){
		return $this->callNetBank('initTransfer');
	}

	/**
	 * Validates the transfer details.
	 *
	 * You must call this before a transaction will be able to be processed.
	 *
	 * @see initTransfer()
	 * @param $fromId
	 * @param $toId
	 * @param $amount
	 * @param string $desc
	 * @return object
	 */
	public function validateTransfer($fromId, $toId, $amount, $desc = ""){
		$amount = number_format($amount, 2, '.', '');

		return $this->callNetBank(
			'validateTransfer',
			[
				'AccountFromId' => $fromId,
				'AccountToId'   => $toId,
				'Description'   => $desc,
				"Amount"        => $amount
			]
		);
	}

	/**
	 * Actually processes the transfer!
	 *
	 * @param $fromId
	 * @param $toId
	 * @param $amount
	 * @param string $desc
	 * @return object
	 */
	public function processTransfer($fromId, $toId, $amount, $desc = ""){
		$amount = number_format($amount, 2, '.', '');

		$this->initialDataStale = true;

		return $this->callNetBank(
			'processTransfer',
			[
				'AccountFromId' => $fromId,
				'AccountToId'   => $toId,
				'Description'   => $desc,
				'Amount'        => $amount
			]
		);
	}

	/**
	 * Allows you to easily transfer money between accounts using account hash. This saves you from having to write the
	 * 3 step code. The $allowOutside bool allows you to define if you want to allow transactions to AccountsToNotLinked
	 * meaning you could transfer money to another persons accounts.
	 *
	 * @param $fromHash
	 * @param $toHash
	 * @param $amount
	 * @param string $desc
	 * @param bool $allowOutside
	 * @return array
	 * @throws Exception
	 */
	public function quickTransfer($fromHash, $toHash, $amount, $desc = '', $allowOutside = false){
		$amount = number_format($amount, 2, '.', '');

		$ret[] = $accounts = $this->initTransfer();
		$accountFromId = null;
		$accountToId = null;

		// Figure out the from ID
		foreach($accounts->AccountsFrom as $accountFrom){
			if($accountFrom->AccountNumberHash === $fromHash){
				$accountFromId = $accountFrom->Id;
				break;
			}
		}

		// Figure out the to ID
		foreach($accounts->AccountsToLinked as $accountTo){
			if($accountTo->AccountNumberHash === $toHash){
				$accountToId = $accountTo->Id;
				break;
			}
		}

		if($allowOutside and is_null($accountToId)){
			foreach($accounts->AccountsToNotLinked as $accountTo){
				if($accountTo->AccountNumberHash === $toHash){
					$accountToId = $accountTo->Id;
					break;
				}
			}
		}

		// Check the IDs are set
		if(is_null($accountToId) or is_null($accountFromId)){
			throw new \Exception('Unable to determine ID');
		}

		$ret[] = $this->validateTransfer($accountFromId, $accountToId, $amount, $desc);
		$ret[] = $this->processTransfer($accountFromId, $accountToId, $amount, $desc);

		return $ret;
	}

	/**
	 * @return object
	 */
	public function initBPay(){
		return $this->callNetBank('initBPay');
	}

	/**
	 * @param $from
	 * @param $billerId
	 * @param $crn
	 * @param $description
	 * @param $amount
	 * @return object
	 */
	public function validateBPay($from, $billerId, $crn, $description, $amount){
		$amount = number_format($amount, 2, '.', '');

		return $this->callNetBank(
			'validateBPay',
			[
				'AccountFromId' => $from,
				'BillerId'      => $billerId,
				'Crn'           => $crn,
				'Description'   => $description,
				'Amount'        => $amount
			]
		);
	}

	/**
	 * @param $from
	 * @param $billerId
	 * @param $crn
	 * @param $description
	 * @param $amount
	 * @return object
	 */
	public function processBPay($from, $billerId, $crn, $description, $amount){
		$amount = number_format($amount, 2, '.', '');

		// Data is stale now download from Netbank
		$this->initialDataStale = true;

		return $this->callNetBank(
			'processBPay',
			[
				'AccountFromId' => $from,
				'BillerId'      => $billerId,
				'Crn'           => $crn,
				'Description'   => $description,
				'Amount'        => $amount
			]
		);
	}


	// ========== Helper Functions ==========

	/**
	 * Turns a string returned by NetBank into a float.
	 *
	 * @param $input
	 * @return float
	 */
	public function parseToFloat($input){
		$ret = substr($input, 1);
		$ret = str_replace(",", "", $ret);
		$cur = trim(substr($input, -3));
		if($cur == "DR"){
			$prefix = "-";
		} else{
			$prefix = "+";
		}

		return floatval($prefix . $ret);
	}

	/**
	 * Clean up the output returned from Netbank and json decode it
	 *
	 * @param $output
	 * @returns object
	 */
	protected function cleanOuput($output){
		$output = trim($output);

		/*
		 * We have to remove the first two and last to chars from the AJAX response because for some reason they return
		 * the response surrounded by comments. Who knows!
		 */
		$output = substr($output, 2, -2);

		return json_decode($output);
	}

	/**
	 * Call Netbank
	 *
	 * @param $req
	 * @param $params
	 * @param $ignoreErrors
	 * @throws Exception
	 * @returns object
	 */
	protected function callNetBank($req, $params = [], $ignoreErrors = false){
		$url = self::netbankURL . '/mobile/i/AjaxCalls.aspx?SID=' . $this->SID;

		$params = array_merge(['Request' => $req], $params);

		$request = new stdClass;

		foreach($params as $k => $v){
			$param = new stdClass();
			$param->Name = $k;
			$param->Value = $v;
			$request->Params[] = $param;
		}

		$request = json_encode($request);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt(
			$ch,
			CURLOPT_USERAGENT,
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9'
		);

		//execute post
		$result = curl_exec($ch);

		$return = $this->cleanOuput($result);

		if(!empty($return->ErrorMessages) and !$ignoreErrors){
			throw new \Exception('NetBank Error(s): ' . implode(', ', $return->ErrorMessages));
		}

		if(isset($return->SID)){
			$this->SID = $return->SID;
		}

		return $return;
	}
}