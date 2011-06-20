<?php

	require_once EXTENSIONS . '/eway/lib/class.api.php';

	Class HostedPaymentsCVNSettings extends PGI_MethodConfiguration {

		/**
		 * Returns the CustomerID depending on the gateway mode.
		 */
		public static function getCustomerId() {
			return (eWayAPI::isTesting())
				? eWayAPI::DEVELOPMENT_CUSTOMER_ID
				: (string)Symphony::Configuration()->get("production-customer-id", 'eway');
		}

		public static function getGatewayURI() {
			return (eWayAPI::isTesting())
				? 'https://www.eway.com.au/gateway_cvn/xmltest/testpage.asp'
				: 'https://www.eway.com.au/gateway_cvn/xmlpayment.asp';
		}

		/**
		 * @link http://www.eway.com.au/Developer/eway-api/cvn-xml.aspx
		 */
		public static function getDefaults() {
			return array(
				'ewayCustomerID' => '',
				'ewayTotalAmount' => '',
				'ewayCustomerFirstName' => '',
				'ewayCustomerLastName' => '',
				'ewayCustomerEmail' => '',
				'ewayCustomerAddress' => '',
				'ewayCustomerPostcode' => '',
				'ewayCustomerInvoiceDescription' => '',
				'ewayCustomerInvoiceRef' => '',
				'ewayCardHoldersName' => '',
				'ewayCardNumber' => '',
				'ewayCardExpiryMonth' => '',
				'ewayCardExpiryYear' => '',
				'ewayTrxnNumber' => '',//returned as ewayTrxnReference. used for tracking. this should be the order id or other internal id.
				'ewayOption1' => '',
				'ewayOption2' => '',
				'ewayOption3' => '',
				'ewayCVN' => ''
			);
		}

		/**
		 * @link http://www.eway.com.au/Developer/eway-api/cvn-xml.aspx
		 */
		public static function getRequiredFields() {
			return array(
				'ewayCustomerID',
				'ewayTotalAmount',
				'ewayCardHoldersName',
				'ewayCardNumber',
				'ewayCardExpiryMonth',
				'ewayCardExpiryYear',
				'ewayCVN'
			);
		}

		/**
		 * @link http://www.eway.com.au/Developer/payment-code/transaction-results-response-codes.aspx
		 */
		public static function getApprovedCodes() {
			return array(
				'00', // Transaction Approved
				'08', // Honour with Identification
				'10', // Approved for Partial Amount
				'11', // Approved, VIP
				'16', // Approved, Update Track 3
			);
		}
	}

	Class HostedPaymentsCVN extends PGI_Request {

		/**
		 * Given an associative array of data, `$values`, this function will merge
		 * with the default eWay fields and send the transaction to eWay's Merchant
		 * Hosted Payments CVN gateway. This function will return an associative array
		 * detailing the success or failure of a transaction.
		 *
		 * @link http://www.eway.com.au/Developer/eway-api/cvn-xml.aspx
		 * @param array $values
		 *  An associative array of field data, usually from $_POST. The key
		 *  should match the key's provided in `$required_fields`
		 * @return array
		 *  An associative array that at minimum contains 'status', 'response-code'
		 *  and 'response-message' keys.
		 */
		public static function processPayment(array $values = array()) {
			// Merge Defaults and passed values
			$request_array = array_merge(HostedPaymentsCVNSettings::getDefaults(), $values);
			$request_array['ewayCustomerID'] = HostedPaymentsCVNSettings::getCustomerID();
			$request_array = array_intersect_key($request_array, HostedPaymentsCVNSettings::getDefaults());

			// Check for missing fields
			$valid_data = true;
			$missing_fields = array();
			$error = null;
			foreach (HostedPaymentsCVNSettings::getRequiredFields() as $field_name) {
				// Don't use empty as sometimes '0' is a valid value
				if (!array_key_exists($field_name, $request_array) || $request_array[$field_name] == '') {
					$missing_fields[] = $field_name;
					$valid_data = false;
				}
			}

			// The data is invalid, return a `DATA_ERROR`
			if(!$valid_data) {
				return new HostedPaymentsCVNResponse(array(
					'status' => __('Data error'),
					'response-code' => PGI_Response::DATA_ERROR,
					'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
					'missing-fields' => $missing_fields
				));
			}

			// If all the required fields exist, lets dance with eWay
			// Generate the XML
			$eway_request_xml = simplexml_load_string('<ewaygateway/>');
			foreach($request_array as $field_name => $field_data) {
				$eway_request_xml->addChild($field_name, General::sanitize($field_data));
			}

			// Start the Gateway
			$curl = PGI_Request::start(HostedPaymentsCVNSettings::getGatewayURI(), $eway_request_xml->asXML());
			$curl_result = $curl->exec();
			$info = $curl->getInfoLast();

			// The Gateway did not connect to eWay successfully
			if(!in_array($info["http_code"], array('200', '201'))) {
				// Return a `GATEWAY_ERROR`
				return new HostedPaymentsCVNResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $info
				));
			}

			// Gateway connected, request sent, lets get the response
			else return new HostedPaymentsCVNResponse($curl_result);
		}
	}

	Class HostedPaymentsCVNResponse extends eWayResponse {

		public function parseResponse($response) {
			// Create a document for the result and load the result
			$eway_result = new DOMDocument('1.0', 'utf-8');
			$eway_result->formatOutput = true;
			$eway_result->loadXML(General::sanitize($response));
			$eway_result_xpath = new DOMXPath($eway_result);

			// Generate status result:
			$eway_transaction_id   = $eway_result_xpath->evaluate('string(/ewayResponse/ewayTrxnNumber)');
			$bank_authorisation_id = $eway_result_xpath->evaluate('string(/ewayResponse/ewayAuthCode)');

			$eway_approved = 'true' == strtolower($eway_result_xpath->evaluate('string(/ewayResponse/ewayTrxnStatus)'));

			// eWay responses come back like 00,Transaction Approved(Test CVN Gateway)
			// It's important to known that although it's called ewayTrxnError, Error's can be 'good' as well
			$eway_return = explode(',', $eway_result_xpath->evaluate('string(/ewayResponse/ewayTrxnError)'), 2);

			// Get the code
			$eway_code = is_numeric($eway_return[0]) ? array_shift($eway_return) : '';
			// Get the response
			$eway_response = preg_replace('/^eWAY Error:\s*/i', '', array_shift($eway_return));

			// Hoorah, we spoke to eway, lets return what they said
			return array(
				'status' => in_array($eway_code, HostedPaymentsCVNSettings::getApprovedCodes()) ? __('Approved') : __('Declined'),
				'response-code' => $eway_code,
				'response-message' => $eway_response,
				'pgi-transaction-id' => $eway_transaction_id,
				'bank-authorisation-id' => $bank_authorisation_id
			);
		}
	}
