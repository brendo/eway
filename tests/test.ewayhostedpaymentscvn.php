<?php

	require_once EXTENSIONS . '/eway/lib/class.api.php';

	/**
	 * eWay Hosted Payments CVN
	 *
	 * Test eWay Hosted Payments CVN API
	 */
	class SymphonyTestEwayHostedPaymentsCVN extends UnitTestCase {

		public function setUp() {
			// Set mode to always be development credentials.
			eWayAPI::setMode('development');
		}

		/**
		 * Missing
		 */
		public function testMissingFields() {
			$fields = array();

			$response = eWayAPI::processPayment($fields);
			$response_array = $response->getResponse();

			$this->assertFalse($response->isSuccessful(),
				'Payment was not successful: %s');

			$this->assertEqual($response->getResponseCode(), PGI_Response::DATA_ERROR,
				'Response code was a Data Error: %s');

			$this->assertPattern('/Missing fields/i', $response->getResponseMessage(),
				'Response message explains missing fields is the error: %s');

			$this->assertEqual(count($response_array['missing-fields']), count(HostedPaymentsCVNSettings::getRequiredFields()) - 1,
				'The missing fields reported matched the fields omitted: %s');
		}

		/**
		 * Invalid
		 */
		public function testInvalidCC() {
			$fields = array(
				'ewayTotalAmount' => 1014,
				'ewayCardHoldersName' => "Thor",
				'ewayCardNumber' => '4444333322221111',
				'ewayCardExpiryMonth' => '05',
				'ewayCardExpiryYear' => '15',
				'ewayCVN' => '111'
			);

			$response = eWayAPI::processPayment($fields);
			$response_array = $response->getResponse();

			$this->assertFalse($response->isSuccessful(),
				'Payment was not successful: %s');

			$this->assertEqual($response->getResponseCode(), 14,
				'Response code is: %s');

			$this->assertPattern('/Invalid card number/i', $response->getResponseMessage(),
				'Response message contains invalid credit card message: %s');
		}

		public function testInvalidCCCVN() {
			$fields = array(
				'ewayTotalAmount' => 1082,
				'ewayCardHoldersName' => "Thor",
				'ewayCardNumber' => '4444333322221111',
				'ewayCardExpiryMonth' => '05',
				'ewayCardExpiryYear' => '15',
				'ewayCVN' => 'abc'
			);

			$response = eWayAPI::processPayment($fields);
			$response_array = $response->getResponse();

			$this->assertFalse($response->isSuccessful(), 
				'Payment was not successful: %s');

			$this->assertEqual($response->getResponseCode(), 82,
				'Response code is: %s');

			$this->assertPattern('/CVV validation|Invalid CVN/i', $response->getResponseMessage(),
				'Response message contains invalid CVV message: %s');
		}

		public function testInvalidCCExpiry() {
			$fields = array(
				'ewayTotalAmount' => 1054,
				'ewayCardHoldersName' => "Thor",
				'ewayCardNumber' => '4444333322221111',
				'ewayCardExpiryMonth' => '05',
				'ewayCardExpiryYear' => '10',
				'ewayCVN' => '111'
			);

			$response = eWayAPI::processPayment($fields);
			$response_array = $response->getResponse();

			$this->assertFalse($response->isSuccessful(),
				'Payment was not successful: %s');

			$this->assertEqual($response->getResponseCode(), 54,
				'Response code is: %s');

			$this->assertPattern('/Expired card|Invalid Expiry Date/i', $response->getResponseMessage(),
				'Response message contains expired card message: %s');
		}

		/**
		 * Declined
		 */
		public function testDeclinedPayment() {
			$fields = array(
				'ewayTotalAmount' => 1005,
				'ewayCardHoldersName' => "Thor",
				'ewayCardNumber' => '4444333322221111',
				'ewayCardExpiryMonth' => '05',
				'ewayCardExpiryYear' => '15',
				'ewayCVN' => '111'
			);

			$response = eWayAPI::processPayment($fields);
			$response_array = $response->getResponse();

			$this->assertFalse($response->isSuccessful(),
				'Payment was not successful: %s');

			$this->assertEqual($response->getResponseCode(), 05,
				'Response code is: %s');

			$this->assertPattern('/Do Not Honour/i', $response->getResponseMessage(),
				'Response message contains declined message: %s');
		}

		/**
		 * Approved
		 */
		public function testApprovedPayment() {
			$fields = array(
				'ewayTotalAmount' => 100,
				'ewayCardHoldersName' => "Thor",
				'ewayCardNumber' => '4444333322221111',
				'ewayCardExpiryMonth' => '05',
				'ewayCardExpiryYear' => '15',
				'ewayCVN' => '111'
			);

			$response = eWayAPI::processPayment($fields);
			$response_array = $response->getResponse();

			$this->assertTrue($response->isSuccessful(),
				'Payment was successful: %s');

			$this->assertEqual($response->getResponseCode(), 00,
				'Response code is: %s');

			$this->assertPattern('/Transaction Approved/i', $response->getResponseMessage(),
				'Response message contains transaction approved: %s');
			
		}

	}