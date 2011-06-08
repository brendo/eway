<?php

	class Extension_eWay extends Extension {

		const TRX_OK = 0;
		const GATEWAY_ERROR = 100;
		const DATA_ERROR = 200;

		public function about() {
			return array(
				'name' => 'eWay',
				'version' => '0.1',
				'release-date' => 'unreleased',
				'author' => array(
					array(
						'name' => 'Brendan Abbott',
						'email' => 'brendan@bloodbone.ws'
					),
					array(
						'name' => 'Henry Singleton'
					)
				),
				'description' => 'Manage and track payments using eWAY.'
			);
		}

		/**
		 * @link http://www.eway.com.au/Developer/eway-api/cvn-xml.aspx
		 */
		private static $defaults = array(
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

		private static $required_fields = array(
			'ewayCustomerID',
			'ewayTotalAmount',
			'ewayCardHoldersName',
			'ewayCardNumber',
			'ewayCardExpiryMonth',
			'ewayCardExpiryYear',
			'ewayCVN'
		);

		/**
		 * @link http://www.eway.com.au/Developer/payment-code/transaction-results-response-codes.aspx
		 */
		private static $approved_codes = array(
			'00', // Transaction Approved
			'08', // Honour with Identification
			'10', // Approved for Partial Amount
			'11', // Approved, VIP
			'16', // Approved, Update Track 3
		);

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function install() {
			Symphony::Configuration()->set('production-customer-id', '', 'eway');
			Symphony::Configuration()->set('production-gateway-uri', 'https://www.eway.com.au/gateway_cvn/xmlpayment.asp', 'eway');
			Symphony::Configuration()->set('development-customer-id', '87654321', 'eway');
			Symphony::Configuration()->set('development-gateway-uri', 'https://www.eway.com.au/gateway_cvn/xmltest/testpage.asp', 'eway');
			Symphony::Configuration()->set('gateway-mode', 'development', 'eway');
			Administration::instance()->saveConfig();
		}

		public function uninstall() {
			Symphony::Configuration()->remove('eway');
			Administration::instance()->saveConfig();
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'appendPreferences'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'Save',
					'callback'	=> 'savePreferences'
				)
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public static function isTesting() {
			return Symphony::Configuration()->get('gateway-mode', 'eway') == 'development';
		}

		public static function getGatewayURI() {
			$mode = self::isTesting() ? 'development' : 'production';
			return (string)Symphony::Configuration()->get("{$mode}-gateway-uri", 'eway');
		}

		public static function getCustomerId() {
			$mode = self::isTesting() ? 'development' : 'production';
			return (string)Symphony::Configuration()->get("{$mode}-customer-id", 'eway');
		}

		public static function getFields($section_id) {
			$sectionManager = new SectionManager(Frontend::instance());
			$section = $sectionManager->fetch($section_id);

			if (!($section instanceof Section)) return array();

			return $section->fetchFields();
		}

		public static function fetchEntries($entries) {
			$result = array();

			if (!is_array($entries)) $entries = array($entries);

			foreach ($entries as $entry) {
				$result[$entry] = self::fetchEntry($entry);
			}

			return $result;
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

	/*-------------------------------------------------------------------------
		Delegate Callbacks:
	-------------------------------------------------------------------------*/

		/**
		 * Allows a user to enter their eWay details to be saved into the Configuration
		 *
		 * @uses AddCustomPreferenceFieldsets
		 */
		public function appendPreferences($context) {
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('eWay')));

			$div = new XMLElement('div', null, array('class' => 'group'));

			// Customer ID
			$label = new XMLElement('label', __('Customer ID'));
			$label->appendChild(
				Widget::Input('settings[eway][production-customer-id]', Symphony::Configuration()->get("production-customer-id", 'eway'))
			);
			$div->appendChild($label);

			// Build the Gateway Mode
			$label = new XMLElement('label', __('Gateway Mode'));
			$options = array(
				array('development', self::isTesting(), __('Development')),
				array('production', !self::isTesting(), __('Production'))
			);

			$label->appendChild(Widget::Select('settings[eway][gateway-mode]', $options));
			$div->appendChild($label);

			$fieldset->appendChild($div);
			$context['wrapper']->appendChild($fieldset);
		}

		/**
		 * Saves the Customer ID and the gateway mode to the configuration
		 *
		 * @uses savePreferences
		 */
		public function savePreferences(array &$context){
			$settings = $context['settings'];

			// Active Section
			Symphony::Configuration()->set('production-customer-id', $settings['eway']['production-customer-id'], 'eway');
			Symphony::Configuration()->set('gateway-mode', $settings['eway']['gateway-mode'], 'eway');

			Administration::instance()->saveConfig();
		}

	/*-------------------------------------------------------------------------
		Process Transaction:
	-------------------------------------------------------------------------*/

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
		public static function processTransaction(array $values = array()) {
			// Merge Defaults and passed values
			$request_array = array_merge(Extension_eWay::$defaults, $values);
			$request_array['ewayCustomerID'] = self::getCustomerID();
			$request_array = array_intersect_key($request_array, Extension_eWay::$defaults);

			// Check for missing fields
			$valid_data = true;
			$missing_fields = array();
			$error = null;
			foreach (Extension_eWay::$required_fields as $field_name) {
				if (!array_key_exists($field_name, $request_array) || empty($request_array[$field_name])) {
					$missing_fields[] = $field_name;
					$valid_data = false;
				}
			}

			// The data is invalid, return a `DATA_ERROR`
			if(!$valid_data) {
				return(array(
					'status' => __('Data error'),
					'response-code' => Extension_eWay::DATA_ERROR,
					'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
					'missing-fields' => $missing_fields
				));
			}

			// If all the required fields exist, lets dance with eWay
			// Generate the XML
			$eway_request_xml = simplexml_load_string('<ewaygateway/>');
			foreach($request_array as $field_name => $field_data) {
				$eway_request_xml->addChild($field_name, $field_data);
			}

			// Curl
			require_once(TOOLKIT . '/class.gateway.php');
			$curl = new Gateway;
			$curl->init(self::getGatewayURI());
			$curl->setopt('POST', true);
			$curl->setopt('POSTFIELDS', $eway_request_xml->asXML());

			$curl_result = $curl->exec();
			$info = $curl->getInfoLast();

			// The Gateway did not connect to eWay successfully
			if(!in_array($info["http_code"], array('200', '201'))) {
				Symphony::$Log->pushToLog($error, E_USER_ERROR, true);

				// Return a `GATEWAY_ERROR`
				return(array(
					'status' => __('Gateway error'),
					'response-code' => Extension_eWay::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.')
				));
			}

			// Gateway connected, request sent, lets get the response
			else {
				// Create a document for the result and load the result
				$eway_result = new DOMDocument('1.0', 'utf-8');
				$eway_result->formatOutput = true;
				$eway_result->loadXML($curl_result);
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
				return(array(
					'status' => in_array($eway_code, Extension_eWay::$approved_codes) ? __('Approved') : __('Declined'),
					'response-code' => $eway_code,
					'response-message' => $eway_response,
					'pgi-transaction-id' => $eway_transaction_id,
					'bank-authorisation-id' => $bank_authorisation_id
				));
			}
		}
	}
