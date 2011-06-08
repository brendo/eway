<?php

	class Extension_eWay extends Extension {

		public function about() {
			return array(
				'name'			=> 'eWay',
				'version'		=> '0.1',
				'release-date'	=> '2011-06-08',
				'author'		=> array(
					array(
						'name'			=> 'Henry Singleton',
						'email'			=> ''
					),
					array(
						'name'			=> 'Brendan Abbott',
						'email'			=> 'brendan@bloodbone.ws'
					)
				),
				'description' => 'Manage and track payments using eWAY.'
			);
		}

		private static $defaults = array(
			'ewayCustomerID'					=> '',
			'ewayTotalAmount'					=> '',
			'ewayCustomerFirstName'				=> '',
			'ewayCustomerLastName'				=> '',
			'ewayCustomerEmail'					=> '',
			'ewayCustomerAddress'				=> '',
			'ewayCustomerPostcode'				=> '',
			'ewayCustomerInvoiceDescription'	=> '',
			'ewayCustomerInvoiceRef'			=> '',
			'ewayCardHoldersName'				=> '',
			'ewayCardNumber'					=> '',
			'ewayCardExpiryMonth'				=> '',
			'ewayCardExpiryYear'				=> '',
			'ewayCVN'							=> '',
			'ewayTrxnNumber'					=> '',//returned as ewayTrxnReference. used for tracking. this should be the order id or other internal id.
			'ewayOption1'						=> '',
			'ewayOption2'						=> '',
			'ewayOption3'						=> '',
			'ewayCVN'							=> ''
		);

		private static $required_fields = array(
			'ewayCustomerID',
			'ewayTotalAmount',
			'ewayCardHoldersName',
			'ewayCardNumber',
			'ewayCardExpiryMonth',
			'ewayCardExpiryYear',
			'ewayCVN'
		);

		private static $approved_codes = array(
			'00',
			'08',
			'10',
			'11',
			'16'
		);

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function install() {
			Symphony::Configuration()->set('production-customer-id', '', 'eway');
			Symphony::Configuration()->set('production-gateway-uri', 'https://www.eway.com.au/gateway_cvn/xmlpayment.asp', 'eway');
			Symphony::Configuration()->set('development-customer-id', '87654321', 'eway');
			Symphony::Configuration()->set('development-gateway-uri', 'https://www.eway.com.au/gateway_cvn/xmltest/testpage.asp', 'eway');
			Symphony::Configuration()->set('development-mode', false, 'eway');
			Administration::instance()->saveConfig();
		}

		public function uninstall() {
			Symphony::Configuration()->remove('eway');
			Administration::instance()->saveConfig();
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public static function isTesting() {
			return (boolean)Symphony::Configuration()->get('development-mode', 'eway');
		}

		public static function getGatewayURI() {
			$mode = $this->isTesting() ? 'development' : 'production';
			return (string)Symphony::Configuration()->get("{$mode}-gateway-uri", 'eway');
		}

		public static function getCustomerId() {
			$mode = $this->isTesting() ? 'development' : 'production';
			return (string)Symphony::Configuration()->get("{$mode}-customer-id", 'eway');
		}

		public static function getFields($section_id) {
			$sectionManager = new SectionManager(Frontend::instance());
			$section = $sectionManager->fetch($section_id);

			if (!($section instanceof Section)) return array();

			return $section->fetchFields();
		}

		public static function fetchEntry($entries) {
			$entryManager = new EntryManager(Frontend::instance());
			$entries = $entryManager->fetch($entries);

			foreach ($entries as $order => $entry) {
				$section_id = $entry->get('section_id');

				$fields = self::getFields($section_id);
				$entry_data = array(
					'id'=> $entry->get('id')
				);

				foreach ($fields as $field) {
					$entry_data[$field->get('element_name')] = $entry->getData($field->get('id'));
				}

				return $entry_data;
			}
		}

		public static function fetchEntries($entries) {
			$result = array();

			if (!is_array($entries)) $entries = array($entries);

			foreach ($entries as $entry) {
				$result[$entry] = self::fetchEntry($entry);
			}

			return $result;
		}

	/*-------------------------------------------------------------------------
		Process Payments:
	-------------------------------------------------------------------------*/

		public function process_transaction(array $values) {
			// Merge Defaults and passed values
			$request_array = array_merge(Extension_eWay::$defaults, $values);
			$request_array['ewayCustomerID'] = self::getCustomerID();

			// Check for missing fields
			$valid_data = true;
			$missing_fields = array();
			$error = null;
			foreach (Extension_eWay::$required_fields as $field_name) {
				if (!array_key_exists($field_name, $request_array) || !empty($request_array[$field_name])) {
					$missing_fields[] = $field_name;
					$valid_data = false;
				}
			}

			// If all the required fields exist, lets dance with eWay
			if ($valid_data) {
				//Generate the XML
				$eway_request_xml = simplexml_load_string('<ewaygateway/>');
				foreach($request_array as $field_name => $field_data) {
					$eway_request_xml->addChild($field_name, $field_data);
				}

				//Curl
				require_once('library/curl.php');
				$curl = new Curl;
				$curl_result = $curl->post(
					$this->getGatewayURI(),
					$eway_request_xml->asXML()
				);

				$error = $curl->error();
				if(!empty($error)) {
					Symphony::$Log->pushToLog($error, E_USER_ERROR, true);

					return(array(
						'status'		=> 'Error',
						'response-code'	=> '888888',
						'response-message'	=> 'There was an error connecting to eWay, you have not been
						charged for this transaction.'
					));
				}
				else {
					// Create a document for the result and load the resul
					$eway_result = new DOMDocument('1.0', 'utf-8');
					$eway_result->formatOutput = true;
					$eway_result->loadXML($curl_result->body);
					$eway_result_xpath = new DOMXPath($eway_result);

					// Generate status result:
					$eway_transaction_id   = $eway_result_xpath->evaluate('string(/ewayResponse/ewayTrxnNumber)');
					$bank_authorisation_id = $eway_result_xpath->evaluate('string(/ewayResponse/ewayAuthCode)');

					$eway_approved = 'true' == strtolower($eway_result_xpath->evaluate('string(/ewayResponse/ewayTrxnStatus)'));
					$eway_error = explode(',', $eway_result_xpath->evaluate('string(/ewayResponse/ewayTrxnError)'), 2);
					$eway_error_code = (is_numeric($eway_error[0]) ? array_shift($eway_error) : '');
					$eway_error_message = preg_replace('/^eWAY Error:\s*/i', '', array_shift($eway_error));

					return(array(
						'pgi-transaction-id'=> $eway_transaction_id,
						'bank-authorisation-id'=> $bank_authorisation_id,
						'status'		=> ($eway_approved ? 'Approved' : 'Declined'),
						'response-code'	=> $eway_error_code,
						'response-message'	=> $eway_error_message
					));
				}
			}

			return(array(
				'status'		=> 'Error',
				'response-code'	=> '999999',
				'response-message'	=> 'Missing Fields: '.implode(', ', $missing_fields),
				'missing-fields'=>	$missing_fields
			));

		}
	}

?>
