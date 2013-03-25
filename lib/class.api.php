<?php

	require_once EXTENSIONS . '/eway/lib/class.apistructure.php';

	Class eWayAPI {

		/**
		 * @link http://www.eway.com.au/Developer/testing/
		 */
		const DEVELOPMENT_CUSTOMER_ID = '87654321';

        /**
         * Constant used for Recurring Payments.
         *
         * @link http://www.eway.com.au/docs/api-documentation/rebill-web-service.pdf?sfvrsn=2
         */
        const DEVELOPMENT_MERCHANT_ID = 'test@eway.com.au';

        /**
         * Constant used for Recurring Payments.
         *
         * @link http://www.eway.com.au/docs/api-documentation/rebill-web-service.pdf?sfvrsn=2
         */
        const DEVELOPMENT_MERCHANT_PASSWORD = 'test123';

	/*-------------------------------------------------------------------------
		Utilities
	-------------------------------------------------------------------------*/

		public static function isTesting() {
			return Symphony::Configuration()->get('gateway-mode', 'eway') == 'development';
		}

	/*-------------------------------------------------------------------------
		API Methods:
	-------------------------------------------------------------------------*/

		public static function processPayment(array $values = array()) {
			require_once EXTENSIONS . '/eway/lib/method.hostedpaymentscvn.php';

			return HostedPaymentsCVN::processPayment($values);
		}

		public static function createRebillCustomerID(array $customer = array()) {
			require_once EXTENSIONS . '/eway/lib/method.recurringpayments.php';
			return RecurringPayments::createRebillCustomer($customer);
		}

		public static function createRebillEvent(array $values = array()) {
			require_once EXTENSIONS . '/eway/lib/method.recurringpayments.php';
			return RecurringPayments::createRebillEvent($values);
		}

		public static function updateRebillEvent(array $values = array()) {
			require_once EXTENSIONS . '/eway/lib/method.recurringpayments.php';
			return RecurringPayments::updateRebillEvent($values);
		}

		public static function queryNextTransaction($rebillCustomerID, $rebillID) {
			require_once EXTENSIONS . '/eway/lib/method.recurringpayments.php';
			return RecurringPayments::queryNextTransaction($rebillCustomerID, $rebillID);
		}

		public static function deleteRebillEvent($rebillCustomerID, $rebillID) {
			require_once EXTENSIONS . '/eway/lib/method.recurringpayments.php';
			return RecurringPayments::deleteRebillEvent($rebillCustomerID, $rebillID);
		}

		public static function queryTransactions($rebillCustomerID, $rebillID) {
			require_once EXTENSIONS . '/eway/lib/method.recurringpayments.php';
			return RecurringPayments::queryTransactions($rebillCustomerID, $rebillID);
		}

		public static function refundTransaction(array $values = array()) {
			require_once EXTENSIONS . '/eway/lib/method.xmlpaymentrefund.php';
			return XMLPaymentRefund::refundTransaction($values);
		}

        /*
         * TOKEN functions.
         *
         * @see https://www.eway.com.au/gateway/ManagedPaymentService/managedCreditCardPayment.asmx
         *
         */
		public static function createCustomer(array $customer = array()) {
			require_once EXTENSIONS . '/eway/lib/method.tokenpayments.php';
			return TokenPayments::createCustomer($customer);
		}

		public static function updateCustomer(array $customer = array()) {
			require_once EXTENSIONS . '/eway/lib/method.tokenpayments.php';
			return TokenPayments::updateCustomer($customer);
		}

		public static function processTokenPayment(array $values = array()) {
			require_once EXTENSIONS . '/eway/lib/method.tokenpayments.php';
			return TokenPayments::processTokenPayment($values);
		}

		public static function queryTokenCustomer(array $values = array()) {
			require_once EXTENSIONS . '/eway/lib/method.tokenpayments.php';
			return TokenPayments::queryTokenCustomer($values);
		}

		public static function ProcessPaymentWithCVN(array $values = array()) {
			require_once EXTENSIONS . '/eway/lib/method.tokenpayments.php';
			return TokenPayments::ProcessPaymentWithCVN($values);
		}

	}

	Abstract Class eWaySettings extends PGI_MethodConfiguration {

		/**
		 * Returns the CustomerID depending on the gateway mode.
		 */
		public static function getCustomerId() {
			return (string)Symphony::Configuration()->get("production-customer-id", 'eway');
		}

		/**
		 * Returns the Merchant Email depending on the gateway mode.
		 */
		public static function getMerchantId() {
			return (string)Symphony::Configuration()->get("production-merchant-id", 'eway');
		}

		/**
		 * Returns the Merchant Password depending on the gateway mode.
		 */
		public static function getMerchantPassword() {
			return  (string)Symphony::Configuration()->get("production-merchant-password", 'eway');
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

	Class eWayResponse extends PGI_Response {
		protected $response = array();
		protected $gateway_response = array();
		protected $xpath = null;
		protected $request = null;

		public function __construct($response, $request = null) {
			if(!is_array($response)) {
				$this->gateway_response = $response;

				if(strlen($response) != 0) {
					$this->response = $this->parseResponse($response);
				}
			}
			else {
				$this->response = $response;
			}

			if(array_key_exists('gateway-response', $this->response)) {
				$this->gateway_response = $this->response['gateway-response'];
				unset($this->response['gateway-response']);
			}

			$this->request = $request;
		}

		public function parseResponse($response) {
			// Create a document for the result and load the result
			$eway_result = new DOMDocument('1.0', 'utf-8');
			$eway_result->formatOutput = true;
			$eway_result->loadXML($response);
			$this->xpath = new DOMXPath($eway_result);

			// Generate status result:
			$eway_transaction_id   = $this->xpath->evaluate('string(/ewayResponse/ewayTrxnNumber)');
			$bank_authorisation_id = $this->xpath->evaluate('string(/ewayResponse/ewayAuthCode)');

			$eway_approved = 'true' == strtolower($this->xpath->evaluate('string(/ewayResponse/ewayTrxnStatus)'));

			// eWay responses come back like 00,Transaction Approved(Test CVN Gateway)
			// It's important to known that although it's called ewayTrxnError, Error's can be 'good' as well
			$eway_return = explode(',', $this->xpath->evaluate('string(/ewayResponse/ewayTrxnError)'), 2);

			// Get the code
			$eway_code = is_numeric($eway_return[0]) ? array_shift($eway_return) : '';
			// Get the response
			$eway_response = preg_replace('/^eWAY Error:\s*/i', '', array_shift($eway_return));

			// Hoorah, we spoke to eway, lets return what they said
			return array(
				'status' => in_array($eway_code, eWaySettings::getApprovedCodes()) ? __('Approved') : __('Declined'),
				'response-code' => $eway_code,
				'response-message' => $eway_response,
				'pgi-transaction-id' => $eway_transaction_id,
				'bank-authorisation-id' => $bank_authorisation_id
			);
		}

	/*-------------------------------------------------------------------------
		Utilities
	-------------------------------------------------------------------------*/

		public function isSuccessful() {
			return is_array($this->response)
				&& array_key_exists('status', $this->response)
				&& $this->response['status'] == __('Approved');
		}

		public function getResponseMessage() {
			return (is_array($this->response) && array_key_exists('response-message', $this->response))
				? $this->response['response-message']
				: null;
		}

		public function getResponse($returnParsedResponse = true) {
			return ($returnParsedResponse === true)
				? $this->response
				: $this->gateway_response;
		}

		public function getRequest() {
			return isset($this->request)
				? $this->request->asXML()
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

		public function addToEventXML(XMLElement $event_xml) {
			if($this->isSuccessful() === false) {
				$event_xml->setAttribute('result', 'error');
				$event_xml->appendChild(
					new XMLElement('eway', $this->getResponseMessage())
				);
			}

			else {
				$xEway = new XMLElement('eway');
				$xEway->appendChild(
					new XMLElement('message', $this->getResponseMessage())
				);

				foreach($this->getSuccess() as $key => $value) {
					$xEway->appendChild(
						new XMLElement($key, $value)
					);
				}

				$event_xml->appendChild($xEway);
			}
		}
	}
