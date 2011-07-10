<?php

	require_once EXTENSIONS . '/eway/lib/class.api.php';

	Class XMLPaymentRefundSettings extends eWaySettings {

		public static function getGatewayURI() {
			return (eWayAPI::isTesting())
				? 'https://www.eway.com.au/gateway/xmlpaymentrefund.asp'
				: 'https://www.eway.com.au/gateway/xmlpaymentrefund.asp';
		}

		/**
		 * @link http://www.eway.com.au/Developer/eway-api/api-refund-credit-card-solution.aspx
		 */
		public static function getDefaults() {
			return array(
				'ewayCustomerID' => '',
				'ewayTotalAmount' => '',
				'ewayCardExpiryMonth' => '',
				'ewayCardExpiryYear' => '',
				'ewayOriginalTrxnNumber' => '',
				'ewayOption1' => '',
				'ewayOption2' => '',
				'ewayOption3' => '',
				'ewayRefundPassword' => '',
				'ewayCustomerInvoiceRef' => ''
			);
		}

		/**
		 * @link http://www.eway.com.au/Developer/eway-api/api-refund-credit-card-solution.aspx
		 */
		public static function getRequiredFields() {
			return array(
				'ewayCustomerID' => '',
				'ewayTotalAmount' => '',
				'ewayCardExpiryMonth' => '',
				'ewayCardExpiryYear' => '',
				'ewayOriginalTrxnNumber' => '',
				'ewayRefundPassword' => ''
			);
		}
	}

	Class XMLPaymentRefund extends PGI_Request {

		/**
		 * Given an associative array of data, `$values`, this function will merge
		 * with the default eWay fields and send the transaction to eWay's XML Payment
		 * Refund gateway. This function will return an associative array
		 * detailing the success or failure of a transaction.
		 *
		 * @link http://www.eway.com.au/Developer/eway-api/api-refund-credit-card-solution.aspx
		 * @param string $refund_password
		 * The refund password defined by you - this is NOT the password used to
		 * login to the eWAY Business Centre.
		 * @param array $values
		 *  An associative array of field data, usually from $_POST. The key
		 *  should match the key's provided in `$required_fields`
		 * @return array
		 *  An associative array that at minimum contains 'status', 'response-code'
		 *  and 'response-message' keys.
		 */
		public static function processPayment($refund_password, array $values = array()) {
			// Merge Defaults and passed values
			$request_array = array_merge(XMLPaymentRefundSettings::getDefaults(), $values);
			$request_array['ewayCustomerID'] = eWaySettings::getCustomerID();
			$request_array['ewayRefundPassword'] = $refund_password;
			$request_array = array_intersect_key($request_array, XMLPaymentRefundSettings::getDefaults());

			// Check for missing fields
			$valid_data = true;
			$missing_fields = array();
			$error = null;
			foreach (XMLPaymentRefundSettings::getRequiredFields() as $field_name) {
				// Don't use empty as sometimes '0' is a valid value
				if (!array_key_exists($field_name, $request_array) || $request_array[$field_name] == '') {
					$missing_fields[] = $field_name;
					$valid_data = false;
				}
			}

			// The data is invalid, return a `DATA_ERROR`
			if(!$valid_data) {
				return new XMLPaymentRefundResponse(array(
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
			$curl = PGI_Request::start(XMLPaymentRefundSettings::getGatewayURI(), $eway_request_xml->asXML());
			$curl_result = $curl->exec();
			$info = $curl->getInfoLast();

			// The Gateway did not connect to eWay successfully
			if(!in_array($info["http_code"], array('200', '201'))) {
				// Return a `GATEWAY_ERROR`
				return new XMLPaymentRefundResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $info
				));
			}

			// Gateway connected, request sent, lets get the response
			else return new XMLPaymentRefundResponse($curl_result);
		}
	}

	Class XMLPaymentRefundResponse extends eWayResponse {

		public function parseResponse($response) {
			$return = parent::parseResponse($response);
			$return['refund_amount'] = $this->xpath->evaluate('string(/ewayResponse/ewayReturnAmount)');

			return $return;
		}

	}
