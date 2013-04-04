<?php

	require_once EXTENSIONS . '/eway/lib/class.api.php';

	/**
	 * eWay Token Payments
	 *
	 * Test eWay Token Payments API
	 */
	class SymphonyTestEwayTokenPayments extends UnitTestCase {

		public function setUp() {
			// Set mode to always be development credentials.
			eWayAPI::setMode('development');
		}

		public function customerFields() {
			$fields = array(
				'Title' => 'Mr.',
				'FirstName' => 'Thor',
				'LastName' => "(Odin's son)",
				'Country' => "au",
				'CCNumber' => '4444333322221111',
				'CCExpiryMonth' => '05',
				'CCExpiryYear' => '15',
				'CVN' => '111'
			);

			return $fields;
		}

		public function paymentFields() {
			$fields = array(
				'amount' => '100',
				'invoiceReference' => 'Test invoice',
				'invoiceDescription' => 'Test description'
			);

			return $fields;
		}

		/**
		 * Create Customer
		 */
		public function testMissingFieldsCreateCustomer() {
			$fields = array();

			$response = eWayAPI::createCustomer($fields);
			$response_array = $response->getResponse();

			$this->assertFalse($response->isSuccessful(),
				'Create customer was not successful: %s');

			$this->assertEqual($response->getResponseCode(), PGI_Response::DATA_ERROR,
				'Response code was a Data Error: %s');

			$this->assertPattern('/Missing fields/i', $response->getResponseMessage(),
				'Response message explains missing fields is the error: %s');

			$this->assertEqual(count($response_array['missing-fields']), count(TokenPaymentsSettings::getRequiredCreateCustomer()),
				'The missing fields reported matched the fields omitted: %s');
		}

		public function testCreateCustomer() {
			$fields = $this->customerFields();

			$response = eWayAPI::createCustomer($fields);
			$this->assertIsA($response, 'string',
				'Customer was created: %s');
		}

		/**
		 * Query customer
		 */
		public function testQueryCustomer() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createCustomer($fields);
			$this->assertIsA($customerID, 'string',
				'Customer was created: %s');

			$response = eWayAPI::queryTokenCustomer(array(
				'managedCustomerID' => $customerID
			));

			$this->assertIsA($response, 'array',
				'Customer was queried successfully: %s');
		}

		/**
		 * Update customer
		 */
		public function testUpdateCustomer() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createCustomer($fields);
			$this->assertIsA($customerID, 'string',
				'Customer was created: %s');

			// Change country
			$fields['Country'] = 'UK';

			$response = eWayAPI::updateCustomer($fields);
			$response_array = $response->getResponse();

			$this->assertEqual(count($response_array['missing-fields']), 1,
				'Detected missing managed customer ID');

			$fields['managedCustomerID'] = $customerID;
			$response = eWayAPI::updateCustomer($fields);

			$this->assertTrue($response,
				'Successfully updated customer');
		}

		/**
		 * Process Token Payment
		 */
		public function testInvalidCustomerIDProcessTokenPayment() {
			$payment_fields = $this->paymentFields();
			$payment_fields['managedCustomerID'] = '111111111111';

			$response = eWayAPI::processTokenPayment($payment_fields);
			$response_array = $response->getResponse();

			$this->assertFalse($response->isSuccessful(),
				'Payment was not successful: %s');

			$this->assertEqual($response->getResponseCode(), PGI_Response::DATA_ERROR,
				'Response code was a Data Error: %s');

			$this->assertPattern('/Invalid managedCustomerID/i', $response->getResponseMessage(),
				'Response message explains that the managedCustomerID is incorrect: %s');
		}

		public function testProcessTokenPayment() {
			// Create Customer
			$fields = $this->customerFields();
			$customerID = eWayAPI::createCustomer($fields);
			$this->assertIsA($customerID, 'string',
				'Customer was created: %s');

			// Do Payment
			$payment_fields = $this->paymentFields();
			$payment_fields['managedCustomerID'] = $customerID;
			$response = eWayAPI::processTokenPayment($payment_fields);
			$response_array = $response->getResponse();

			$this->assertTrue($response->isSuccessful(),
				'Payment was successful: %s');

			$this->assertEqual($response->getResponseCode(), 00,
				'Response code is: %s');

			$this->assertPattern('/Transaction Approved/i', $response->getResponseMessage(),
				'Response message contains transaction approved: %s');
		}

		/**
		 * Process Token Payment with CVN
		 */
		public function testInvalidNoCVNProcessTokenPaymentWithCVN() {
			// Create Customer
			$fields = $this->customerFields();
			$customerID = eWayAPI::createCustomer($fields);
			$this->assertIsA($customerID, 'string',
				'Customer was created: %s');

			// Do Payment
			$payment_fields = $this->paymentFields();
			$payment_fields['managedCustomerID'] = $customerID;
			$response = eWayAPI::processTokenPaymentWithCVN($payment_fields);
			$response_array = $response->getResponse();

			$this->assertFalse($response->isSuccessful(),
				'Payment was not successful: %s');

			$this->assertEqual($response->getResponseCode(), PGI_Response::DATA_ERROR,
				'Response code was a Data Error: %s');

			$this->assertPattern('/Missing fields/i', $response->getResponseMessage(),
				'Response message explains missing fields is the error: %s');

			$this->assertEqual(count($response_array['missing-fields']), 1,
				'The missing fields reported matched the fields omitted: %s');
		}

		public function testInvalidCustomerIDProcessTokenPaymentWithCVN() {
			$payment_fields = $this->paymentFields();
			$payment_fields['managedCustomerID'] = '111111111111';
			$payment_fields['cvn'] = '111';

			$response = eWayAPI::processTokenPaymentWithCVN($payment_fields);
			$response_array = $response->getResponse();

			$this->assertFalse($response->isSuccessful(),
				'Payment was not successful: %s');

			$this->assertEqual($response->getResponseCode(), PGI_Response::DATA_ERROR,
				'Response code was a Data Error: %s');

			$this->assertPattern('/Invalid managedCustomerID/i', $response->getResponseMessage(),
				'Response message explains that the managedCustomerID is incorrect: %s');
		}

		public function testProcessTokenPaymentWithCVN() {
			$fields = $this->customerFields();

			$customerID = eWayAPI::createCustomer($fields);
			$this->assertIsA($customerID, 'string',
				'Customer was created: %s');

			// Do Payment
			$payment_fields = $this->paymentFields();
			$payment_fields['managedCustomerID'] = $customerID;
			$payment_fields['cvn'] = '111';
			$response = eWayAPI::processTokenPaymentWithCVN($payment_fields);
			$response_array = $response->getResponse();

			$this->assertTrue($response->isSuccessful(),
				'Payment was successful: %s');

			$this->assertEqual($response->getResponseCode(), 00,
				'Response code is: %s');

			$this->assertPattern('/Transaction Approved/i', $response->getResponseMessage(),
				'Response message contains transaction approved: %s');
		}
	}