<?php

	require_once EXTENSIONS . '/eway/lib/class.api.php';
	require_once EXTENSIONS . '/eway/lib/method.recurringpayments.php';

	/**
	 * eWay Recurring Payments
	 *
	 * Test eWay Recurring Payments API
	 */
	class SymphonyTestEwayRecurringPayments extends UnitTestCase {

		public function setUp() {
			// Set mode to always be development credentials.
			eWayAPI::setMode('development');
		}

		public function customerFields() {
			$fields = array(
				'customerTitle'		=> 'Mr.',
				'customerFirstName' => 'Thor',
				'customerLastName'	=> "(Odin's son)",
				'customerEmail'		=> 'test@example.com',
				'customerCountry'	=> 'au',
			);

			return $fields;
		}

		public function paymentFields() {
			$fields = array(
				'RebillCCName'		=> "Thor (Odin's son)",
				'RebillCCNumber'	=> '4444333322221111',
				'RebillCCExpMonth'	=> '05',
				'RebillCCExpYear'	=> '15',
				'RebillInitAmt'		=> '100',
				'RebillRecurAmt'	=> '100',
				'frequency'			=> 'weekly', // parsed by getFrequency
				'RebillInitDate'	=> '',
				'RebillStartDate'	=> '',
				'RebillEndDate'		=> ''
			);

			return $fields;
		}

		/**
		 * Create Rebill Customer
		 */
		public function testFailedCreateRebillCustomer() {
			$fields = $this->customerFields();
			unset($fields['customerEmail']);

			$response = eWayAPI::createRebillCustomerID($fields);
			$response_array = $response->getResponse();

			$this->assertFalse($response->isSuccessful(),
				'Create Rebill Customer was not successful: %s');

			$this->assertEqual($response->getResponseCode(), PGI_Response::DATA_ERROR,
				'Response code was a Data Error: %s');

			$this->assertPattern('/Missing fields/i', $response->getResponseMessage(),
				'Response message explains missing fields is the error: %s');

			$this->assertEqual(count($response_array['missing-fields']), 1,
				'The missing fields reported matches the missing fields: %s');

			$this->assertEqual($response_array['missing-fields'][0], 'customerEmail',
				'The missing fields is customerEmail: %s');
		}

		public function testCreateRebillCustomer() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');
		}

		/**
		 * Update Rebill Customer
		 */
		public function testFailedUpdateRebillCustomer() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);
			$fields['customerCompany'] = 'Marvel';

			$response = eWayAPI::updateRebillCustomer($fields);
			$response_array = $response->getResponse();

			$this->assertFalse($response->isSuccessful(),
				'Create Rebill Customer was not successful: %s');

			$this->assertEqual($response->getResponseCode(), PGI_Response::DATA_ERROR,
				'Response code was a Data Error: %s');

			$this->assertPattern('/Missing fields/i', $response->getResponseMessage(),
				'Response message explains missing fields is the error: %s');

			$this->assertEqual(count($response_array['missing-fields']), 1,
				'The missing fields reported matches the missing fields: %s');

			$this->assertEqual($response_array['missing-fields'][0], 'RebillCustomerID',
				'The missing fields is RebillCustomerID: %s');
		}

		public function testUpdateRebillCustomer() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$fields['RebillCustomerID'] = $customerID;
			$fields['customerCompany'] = 'Marvel';

			$response = eWayAPI::updateRebillCustomer($fields);

			$this->assertTrue($response,
				'Update Rebill customer was successful: %s');
		}

		/**
		 * Delete Rebill Customer
		 */
		public function testDeleteRebillCustomer() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');

			$response = eWayAPI::deleteRebillCustomer($customerID);

			$this->assertTrue($response,
				'Delete Rebill customer was successful: %s');
		}

		/**
		 * Query Rebill Customer
		 */
		public function testQueryRebillCustomer() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');

			$response = eWayAPI::queryRebillCustomer($customerID);

			$this->assertNotEqual(count($response), 0,
				'Rebill Customer exists: %s');
		}

		/**
		 * Create Rebill Event
		 */
		public function testFailedCreateRebillEvent() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');

			$payment = $this->paymentFields();
			$payment['RebillCustomerID'] = $customerID;
			$payment['RebillInitDate'] = 'May 5th 2013';
			$payment['RebillStartDate'] = 'May 6th 2013';
			$payment['RebillEndDate'] = 'May 5th 2013'; // End date before start date

			$response = eWayAPI::createRebillEvent($payment);

			$this->assertFalse($response->isSuccessful(),
				'Rebill event was not successful: %s');

			$this->assertEqual($response->getResponseCode(), PGI_Response::GATEWAY_ERROR,
				'Response code was a Gateway Error: %s');

			$this->assertPattern('/\'RebillEndDate\' must be after \'RebillStartDate\'/i', $response->getResponseMessage(),
				'Response message explains the error: %s');
		}

		public function testCreateRebillEvent() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');

			$payment = $this->paymentFields();
			$payment['RebillCustomerID'] = $customerID;
			$payment['RebillInitDate'] = 'May 5th 2013';
			$payment['RebillStartDate'] = 'May 6th 2013';
			$payment['RebillEndDate'] = 'May 5th 2015';

			$response = eWayAPI::createRebillEvent($payment);

			$this->assertIsA($response, 'string',
				'Rebill Event ID was successful: %s');
		}

		/**
		 * Update Rebill Event
		 */
		public function testFailedUpdateRebillEvent() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');

			$payment = $this->paymentFields();
			$payment['RebillCustomerID'] = $customerID;
			$payment['RebillInitDate'] = 'May 5th 2013';
			$payment['RebillStartDate'] = 'May 6th 2013';
			$payment['RebillEndDate'] = 'May 5th 2015';

			$rebillID = eWayAPI::createRebillEvent($payment);

			$this->assertIsA($rebillID, 'string',
				'Rebill Event ID was successful: %s');

			$payment['RebillCCExpMonth'] = 'cats';
			$payment['RebillID'] = $rebillID;
			$response = eWayAPI::createRebillEvent($payment);

			$this->assertFalse($response->isSuccessful(),
				'Update Rebill event was not successful: %s');

			$this->assertEqual($response->getResponseCode(), PGI_Response::GATEWAY_ERROR,
				'Response code was a Gateway Error: %s');

			$this->assertPattern('/\'RebillCCExpMonth\'/i', $response->getResponseMessage(),
				'Response message explains the error: %s');
		}

		public function testUpdateRebillEvent() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');

			$payment = $this->paymentFields();
			$payment['RebillCustomerID'] = $customerID;
			$payment['RebillInitDate'] = 'May 5th 2013';
			$payment['RebillStartDate'] = 'May 6th 2013';
			$payment['RebillEndDate'] = 'May 5th 2015';

			$rebillID = eWayAPI::createRebillEvent($payment);

			$this->assertIsA($rebillID, 'string',
				'Rebill Event ID was successful: %s');

			$payment['RebillID'] = $rebillID;
			$response = eWayAPI::createRebillEvent($payment);

			$this->assertTrue($response,
				'Update Rebill event was successful: %s');
		}

		/**
		 * Query Rebill Event
		 */
		public function testQueryRebillEvent() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');

			$payment = $this->paymentFields();
			$payment['RebillCustomerID'] = $customerID;
			$payment['RebillInitDate'] = 'May 5th 2013';
			$payment['RebillStartDate'] = 'May 6th 2013';
			$payment['RebillEndDate'] = 'May 5th 2015';

			$rebillID = eWayAPI::createRebillEvent($payment);

			$this->assertIsA($rebillID, 'string',
				'Rebill Event ID was successful: %s');

			$response = eWayAPI::queryRebillEvent($customerID, $rebillID);
			var_dump($response);
			$this->assertNotEqual(count($response), 0,
				'Rebill Event exists: %s');
		}

		/**
		 * Delete Rebill Event
		 */
		public function testFailedDeleteRebillEvent() {
			$response = eWayAPI::deleteRebillEvent('132423', false);

			$this->assertFalse($response->isSuccessful(),
				'Delete Rebill event was not successful: %s');

			$this->assertEqual($response->getResponseCode(), PGI_Response::DATA_ERROR,
				'Response code was a Data Error: %s');

			$this->assertPattern('/Missing parameters/i', $response->getResponseMessage(),
				'Response message explains the error: %s');
		}

		public function testDeleteRebillEvent() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');

			$payment = $this->paymentFields();
			$payment['RebillCustomerID'] = $customerID;
			$payment['RebillInitDate'] = 'May 5th 2013';
			$payment['RebillStartDate'] = 'May 6th 2013';
			$payment['RebillEndDate'] = 'May 5th 2015';

			$rebillID = eWayAPI::createRebillEvent($payment);

			$this->assertIsA($rebillID, 'string',
				'Rebill Event ID was successful: %s');

			$response = eWayAPI::deleteRebillEvent($customerID, $rebillID);

			$this->assertTrue($response,
				'Delete Rebill event was successful: %s');
		}

		/**
		 * Query Transactions
		 */
		public function testQueryTransactions() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');

			$payment = $this->paymentFields();
			$payment['RebillCustomerID'] = $customerID;
			$payment['RebillInitDate'] = 'May 5th 2013';
			$payment['RebillStartDate'] = 'May 6th 2013';
			$payment['RebillEndDate'] = 'May 5th 2015';

			$rebillID = eWayAPI::createRebillEvent($payment);

			$this->assertIsA($rebillID, 'string',
				'Rebill Event ID was successful: %s');

			$transactions = eWayAPI::queryTransactions($customerID, $rebillID);

			$this->assertNotEqual(count($transactions), 0,
				'Query Transactions exist: %s');
		}

		public function testQueryTransactionsWithFilters() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');

			$payment = $this->paymentFields();
			$payment['RebillCustomerID'] = $customerID;
			$payment['RebillInitDate'] = 'May 5th 2013';
			$payment['RebillStartDate'] = 'May 6th 2013';
			$payment['RebillEndDate'] = 'May 5th 2015';

			$rebillID = eWayAPI::createRebillEvent($payment);

			$this->assertIsA($rebillID, 'string',
				'Rebill Event ID was successful: %s');

			$transactions = eWayAPI::queryTransactions($customerID, $rebillID, array(
				'status' => 'Future'
			));

			$this->assertNotEqual(count($transactions), 0,
				'Query Transactions exist: %s');

			$only_future = true;
			foreach($transactions as $transaction) {
				if($transaction['Status'] !== 'Future') {
					$only_future = false;
				}
			}

			$this->assertTrue($only_future,
				'All Query Transactions returned are marked Future');
		}

		/**
		 * Query Next Transactions
		 */
		public function testQueryNextTransaction() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createRebillCustomerID($fields);

			$this->assertIsA($customerID, 'string',
				'Rebill Customer was created: %s');

			$payment = $this->paymentFields();
			$payment['RebillCustomerID'] = $customerID;
			$payment['RebillInitDate'] = 'May 5th 2013';
			$payment['RebillStartDate'] = 'May 6th 2013';
			$payment['RebillEndDate'] = 'May 5th 2015';

			$rebillID = eWayAPI::createRebillEvent($payment);

			$this->assertIsA($rebillID, 'string',
				'Rebill Event ID was successful: %s');

			$transaction = eWayAPI::queryNextTransaction($customerID, $rebillID);

			$this->assertNotNull($transaction['TransactionDate'],
				'Transaction Date is set: %s');
			$this->assertNotNull($transaction['CardHolderName'],
				'CardHolder Name is set: %s');
			$this->assertNotNull($transaction['ExpiryDate'],
				'Expiry Date is set: %s');
			$this->assertNotNull($transaction['Amount'],
				'Amount is set: %s');
			$this->assertNotNull($transaction['Status'],
				'Status is set: %s');
			$this->assertNotNull($transaction['Type'],
				'Status is set: %s');
		}

		/**
		 * Date parsing
		 */
		public function testParseInvalidDate() {
			$date = RecurringPayments::parseDate('daily');

			$this->assertFalse($date,
				'Invalid date given: %s');
		}

		public function testParseValidDate() {
			$date = RecurringPayments::parseDate('May 5th, 2013');

			$this->assertEqual($date, '05/05/2013',
				'Parsed date was correct: %s');
		}

		public function testParseValidDateQuery() {
			$date = RecurringPayments::parseDate('May 5th, 2013', 'query');

			$this->assertEqual($date, '2013-05-05',
				'Parsed date was correct: %s');
		}

		public function testParseInvalidDates() {
			$values = array(
				'RebillInitDate' => 'daily',
				'RebillStartDate' => 'daily',
				'RebillEndDate' => 'daily',
			);
			$dates = RecurringPayments::parseDates($values);

			$this->assertIsA($dates, 'RecurringPaymentsResponse',
				'Invalid dates set: %s');
		}

		public function testParseValidDates() {
			$values = array(
				'RebillInitDate' => 'May 5th, 2013',
				'RebillStartDate' => 'May 5th, 2013',
				'RebillEndDate' => 'May 5th, 2013',
			);
			$dates = RecurringPayments::parseDates($values);

			$this->assertEqual(count($values), 3,
				'Dates were parsed correctly: %s');
		}

		/**
		 * Get Frequency
		 */
		public function testGetInvalidFrequency() {
			$frequency = RecurringPayments::getFrequency('daily');

			$this->assertEqual(count($frequency), 0,
				'No Rebill Frequency is set: %s');
		}

		public function testGetWeeklyFrequency() {
			$frequency = RecurringPayments::getFrequency('weekly');

			$this->assertEqual($frequency['RebillInterval'], 1,
				'Weekly Frequency Rebill Interval is set: %s');
			$this->assertEqual($frequency['RebillIntervalType'], RecurringPaymentsSettings::WEEK,
				'Weekly Frequency Rebill Interval Type is set: %s');
		}

		public function testGetFortnightlyFrequency() {
			$frequency = RecurringPayments::getFrequency('fortnightly');

			$this->assertEqual($frequency['RebillInterval'], 2,
				'Fortnightly Frequency Rebill Interval is set: %s');
			$this->assertEqual($frequency['RebillIntervalType'], RecurringPaymentsSettings::WEEK,
				'Fortnightly Frequency Rebill Interval Type is set: %s');
		}

		public function testGetMonthlyFrequency() {
			$frequency = RecurringPayments::getFrequency('monthly');

			$this->assertEqual($frequency['RebillInterval'], 1,
				'Monthly Frequency Rebill Interval is set: %s');
			$this->assertEqual($frequency['RebillIntervalType'], RecurringPaymentsSettings::MONTH,
				'Monthly Frequency Rebill Interval Type is set: %s');
		}

		public function testGetYearlyFrequency() {

			$frequency = RecurringPayments::getFrequency('yearly');

			$this->assertEqual($frequency['RebillInterval'], 1,
				'Yearly Frequency Rebill Interval is set: %s');
			$this->assertEqual($frequency['RebillIntervalType'], RecurringPaymentsSettings::YEAR,
				'Yearly Frequency Rebill Interval Type is set: %s');
		}

		public function testParseInvalidFrequency() {
			$values = array(
				'frequency' => 'daily'
			);
			$frequency = RecurringPayments::parseFrequencyValue($values);

			$this->assertIsA($frequency, 'RecurringPaymentsResponse',
				'Invalid Rebill Frequency is set: %s');
		}

		public function testParseValidFrequency() {
			$values = array(
				'frequency' => 'monthly'
			);
			$frequency = RecurringPayments::parseFrequencyValue($values);

			$this->assertEqual(count($values), 2,
				'Frequency was parsed correctly: %s');
		}
	}

