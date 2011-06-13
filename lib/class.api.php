<?php

	Abstract Class PGI_MethodConfiguration {
		abstract static function getGatewayURI();
		abstract static function getDefaults();
		abstract static function getRequiredFields();
		abstract static function getApprovedCodes();
	}

	Abstract Class PGI_Request {
		public function start($uri, $xml) {
			// Curl
			require_once(TOOLKIT . '/class.gateway.php');
			$curl = new Gateway;
			$curl->init($uri);
			$curl->setopt('POST', true);
			$curl->setopt('POSTFIELDS', $xml);

			return $curl;
		}
	}

	Abstract Class PGI_Response {
		const GATEWAY_ERROR = 100;
		const DATA_ERROR = 200;
	}

	Class eWayAPI {

		/**
		 * @link http://www.eway.com.au/Developer/testing/
		 */
		const DEVELOPMENT_CUSTOMER_ID = '87654321';

	/*-------------------------------------------------------------------------
		Utilities
	-------------------------------------------------------------------------*/

		public static function isTesting() {
			return Symphony::Configuration()->get('gateway-mode', 'eway') == 'development';
		}

	/*-------------------------------------------------------------------------
		Process Transaction:
	-------------------------------------------------------------------------*/

		public static function processPayment(array $values = array()) {
			require_once EXTENSIONS . '/eway/lib/class.xmlpayment.php';

			return XMLPayment::processPayment();
		}

	}

	Class eWayResponse extends PGI_Response {
		private $response = array();
		private $gateway_response = array();

		public function __construct($response) {
			if(!is_array($response)) {
				$this->gateway_response = $response;
				$this->parseResponse($response);
			}
			else {
				$this->response = $response;
			}
		}

	/*-------------------------------------------------------------------------
		Utilities
	-------------------------------------------------------------------------*/

		public function isSuccessful() {
			return is_array($this->response)
				&& array_key_exists('status', $this->response)
				&& $this->response['status'] == __('Approved');
		}

		public function getError() {
			return (is_array($this->response) && array_key_exists('response-message', $this->response))
				? $this->response['response-message']
				: null;
		}

		public function getSuccess() {
			return ($this->isSuccessful())
				? array(
						'transaction-id' => $this->response['pgi-transaction-id'],
						'bank-authorisation-id' => $this->response['bank-authorisation-id']
					)
				: false;
		}

		public function getResponse($returnParsedResponse = true) {
			return ($returnParsedResponse === true)
				? $this->response
				: $this->gateway_response;
		}

		public function addToEventXML(XMLElement $event_xml) {
			if(!$this->isSuccessful()) {
				$event_xml->setAttribute('result', 'error');
				$event_xml->appendChild(
					new XMLElement('eway', $this->getError())
				);
			}

			else {
				$xEway = new XMLElement('eway');

				foreach($this->getSuccess() as $key => $value) {
					$xEway->appendChild(
						new XMLElement($key, $value)
					);
				}

				$event_xml->appendChild($xEway);
			}
		}
	}


