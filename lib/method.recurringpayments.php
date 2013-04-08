<?php

	require_once EXTENSIONS . '/eway/lib/class.api.php';

	Class RecurringPaymentsSettings extends eWaySettings {

		/**
		 * Constants for Interval Type
		 * @see http://www.eway.com.au/docs/api-documentation/rebill-web-service.pdf?sfvrsn=2
		 */
		const DAY = 1;
		const WEEK = 2;
		const MONTH = 3;
		const YEAR = 4;

		public static function getGatewayURI() {
			return (eWayAPI::isTesting())
				? 'https://www.eway.com.au/gateway/rebill/test/managerebill_test.asmx'
				: 'https://www.eway.com.au/gateway/rebill/manageRebill.asmx';
		}

		public static function getSoapNamespace() {
			return 'http://www.eway.com.au/gateway/rebill/manageRebill';
		}

		/**
		 * Get the default fields for the Rebill Customer
		 * call. eWay requires that all fields be sent, even if they
		 * are left empty.
		 * @return array
		 */
		public static function getDefaultRebillCustomerFields() {
			return array(
				'customerTitle'		=> '',
				'customerFirstName' => '',
				'customerLastName'	=> '',
				'customerAddress'	=> '',
				'customerSuburb'	=> '',
				'customerState'		=> '',
				'customerCompany'	=> '',
				'customerPostCode'	=> '',
				'customerCountry'	=> '',
				'customerEmail'		=> '',
				'customerFax'		=> '',
				'customerPhone1'	=> '',
				'customerPhone2'	=> '',
				'customerRef'		=> '',
				'customerJobDesc'	=> '',
				'customerComments'	=> '',
				'customerURL'		=> ''
			);
		}

		/**
		 * Required fields to create a rebill customer.
		 * @return array
		 */
		public static function getRequiredCreateRebillCustomer() {
			return array(
				'customerTitle',
				'customerFirstName',
				'customerLastName',
				'customerEmail'
			);
		}

		/**
		 * Required fields to update a rebill customer.
		 * @return array
		 */
		public static function getRequiredUpdateRebillCustomer() {
			$required_update_customer = self::getRequiredCreateRebillCustomer();
			$required_update_customer[] = 'RebillCustomerID';

			return $required_update_customer;
		}

		/**
		 * Get the default fields for the Rebill Events
		 * call. eWay requires that all fields be sent, even if they
		 * are left empty.
		 * @return array
		 */
		public static function getDefaultRebillEventFields() {
			return array(
				'RebillCustomerID'	=> '',
				'RebillInvRef'		=> '',
				'RebillInvDes'		=> '',
				'RebillCCName'		=> '',
				'RebillCCNumber'	=> '',
				'RebillCCExpMonth'	=> '',
				'RebillCCExpYear'	=> '',
				'RebillInitAmt'		=> '',
				'RebillInitDate'	=> '',
				'RebillRecurAmt'	=> '',
				'RebillStartDate'	=> '',
				'RebillInterval'	=> '',
				'RebillIntervalType'=> '',
				'RebillEndDate'		=> ''
			);
		}

		/**
		 * Required fields to create a rebill event.
		 * @return array
		 */
		public static function getRequiredCreateRebillEvent() {
			return array(
				'RebillCustomerID',
				'RebillCCName',
				'RebillCCNumber',
				'RebillCCExpMonth',
				'RebillCCExpYear',
				'RebillInitAmt',
				'RebillInitDate',
				'RebillRecurAmt',
				'RebillStartDate',
				'RebillInterval',
				'RebillIntervalType',
				'RebillEndDate',
			);
		}

		/**
		 * Required fields to update a rebill event.
		 * @return array
		 */
		public static function getRequiredUpdateRebillEvent() {
			$required_rebill = self::getRequiredCreateRebillEvent();
			$required_rebill[] = 'RebillID';

			return $required_rebill;
		}

	}

	Class RecurringPayments extends Recurring_Request {

		/**
		 * Create a new Rebill Customer.
		 *
		 * @param array $values
		 *  Array of values containing the Customer's details to create a new rebill customer.
		 * @return RebillCustomerID
		 */
		public static function createRebillCustomer(array $values = array()) {
			// Merge Defaults and passed values
			$request_array = array_merge(RecurringPaymentsSettings::getDefaultRebillCustomerFields(), $values);

			// Check for missing fields
			$valid_data = true;
			$missing_fields = array();
			foreach (RecurringPaymentsSettings::getRequiredCreateRebillCustomer() as $field_name) {
				if (!array_key_exists($field_name, $request_array) || $request_array[$field_name] == '') {
					$missing_fields[] = $field_name;
					$valid_data = false;
				}
			}

			// The data is invalid, return a `DATA_ERROR`
			if(!$valid_data) {
				return new RecurringPaymentsResponse(array(
					'status' => __('Data error'),
					'response-code' => PGI_Response::DATA_ERROR,
					'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
					'missing-fields' => $missing_fields
				));
			}

			$eway_request_xml = simplexml_load_string('<CreateRebillCustomer xmlns="' . RecurringPaymentsSettings::getSoapNamespace() . '" />');
			foreach($request_array as $field_name => $field_data) {
				$eway_request_xml->addChild($field_name, General::sanitize($field_data));
			}

			// Execute the transaction.
			$ch = Recurring_Request::start(RecurringPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());

			$curl_result = curl_exec($ch);
			$info = curl_getinfo($ch);

			if (curl_errno($ch)) {
				// The Gateway did not connect to eWay successfully or some error occurred.
				return new RecurringPaymentsResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $info
				), $eway_request_xml);
			}
			else {
				curl_close($ch);

				$response = new RecurringPaymentsResponse($curl_result, $eway_request_xml);
				$xpath = $response->parseGatewayResponse($curl_result);

				// If successful, return the $customerID, otherwise return the $response object
				if($response->isSuccessful()) {
					$customerId = $xpath->evaluate('string(//eway:RebillCustomerID)');
					return $customerId;
				}
				else {
					return $response;
				}
			}
		}

		/**
		 * Update an existing Rebill Customer.
		 *
		 * @param array $values
		 *  Array of values containing the Customer's details to update a rebill customer.
		 * @return RebillCustomerID
		 */
		public static function updateRebillCustomer(array $values = array()) {
			// Merge Defaults and passed values
			$request_array = array_merge(RecurringPaymentsSettings::getDefaultRebillCustomerFields(), $values);

			// Check for missing fields
			$valid_data = true;
			$missing_fields = array();
			foreach (RecurringPaymentsSettings::getRequiredUpdateRebillCustomer() as $field_name) {
				if (!array_key_exists($field_name, $request_array) || $request_array[$field_name] == '') {
					$missing_fields[] = $field_name;
					$valid_data = false;
				}
			}

			// The data is invalid, return a `DATA_ERROR`
			if(!$valid_data) {
				return new RecurringPaymentsResponse(array(
					'status' => __('Data error'),
					'response-code' => PGI_Response::DATA_ERROR,
					'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
					'missing-fields' => $missing_fields
				));
			}

			$eway_request_xml = simplexml_load_string('<UpdateRebillCustomer xmlns="' . RecurringPaymentsSettings::getSoapNamespace() . '" />');
			foreach($request_array as $field_name => $field_data) {
				$eway_request_xml->addChild($field_name, General::sanitize($field_data));
			}

			// Execute the transaction.
			$ch = Recurring_Request::start(RecurringPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());

			$curl_result = curl_exec($ch);
			$info = curl_getinfo($ch);

			if (curl_errno($ch)) {
				// The Gateway did not connect to eWay successfully or some error occurred.
				return new RecurringPaymentsResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $info
				), $eway_request_xml);
			}
			else {
				curl_close($ch);

				$response = new RecurringPaymentsResponse($curl_result, $eway_request_xml);
				$xpath = $response->parseGatewayResponse($curl_result);

				// If successful, return the $customerID, otherwise return the $response object
				if($response->isSuccessful()) {
					$customerId = $xpath->evaluate('string(//eway:RebillCustomerID)');
					return $customerId;
				}
				else {
					return $response;
				}
			}
		}

		/**
		 * Delete existing rebill customer
		 *
		 * @param string $rebillCustomerID
		 *
		 * @return boolean
		 */
		public static function deleteRebillCustomer($rebillCustomerID) {
			$params_exist = self::parametersExist($rebillCustomerID);
			if(is_a($params_exist, 'RecurringPaymentsResponse')) {
				return $params_exist;
			}

			$eway_request_xml = simplexml_load_string('<DeleteRebillCustomer xmlns="' . RecurringPaymentsSettings::getSoapNamespace() . '" />');
			$eway_request_xml->addChild('RebillCustomerID', General::sanitize($rebillCustomerID));

			// Execute the transaction.
			$ch = Recurring_Request::start(RecurringPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());

			$curl_result = curl_exec($ch);
			$status = curl_getinfo($ch);

			if (curl_errno($ch)) {
				// The Gateway did not connect to eWay successfully or some error occurred.
				return new RecurringPaymentsResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $status
				), $eway_request_xml);
			}
			else {

				curl_close($ch);

				$response = new RecurringPaymentsResponse($curl_result, $eway_request_xml);
				$xpath = $response->parseGatewayResponse($curl_result);

				// If successful, return the true, otherwise $response object
				if($response->isSuccessful()) {
					return true;
				}
				else {
					return $response;
				}
			}
		}

		/**
		 * Query existing rebill customer
		 *
		 * @param string $rebillCustomerID
		 * @return boolean
		 */
		public static function queryRebillCustomer($rebillCustomerID) {
			$params_exist = self::parametersExist($rebillCustomerID);
			if(is_a($params_exist, 'RecurringPaymentsResponse')) {
				return $params_exist;
			}

			$eway_request_xml = simplexml_load_string('<QueryRebillCustomer xmlns="' . RecurringPaymentsSettings::getSoapNamespace() . '" />');
			$eway_request_xml->addChild('RebillCustomerID', General::sanitize($rebillCustomerID));

			// Execute the transaction.
			$ch = Recurring_Request::start(RecurringPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());

			$curl_result = curl_exec($ch);
			$status = curl_getinfo($ch);

			if (curl_errno($ch)) {
				// The Gateway did not connect to eWay successfully or some error occurred.
				return new RecurringPaymentsResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $status
				), $eway_request_xml);
			}
			else {

				curl_close($ch);

				$response = new RecurringPaymentsResponse($curl_result, $eway_request_xml);
				$xpath = $response->parseGatewayResponse($curl_result);

				// If successful, return the true, otherwise $response object
				if($response->isSuccessful()) {
					$customerResult = $xpath->query('//eway:QueryRebillCustomerResult');

					// Build an array with old/future transactions.
					$details = array();
					foreach($customerResult as $node) {
						foreach ($node->childNodes as $child) {
							if(in_array($child->nodeName, array('Result', 'ErrorSeverity', 'ErrorDetails'))) {
								continue;
							}

							$details[$child->nodeName] = $child->nodeValue;
						}
					}

					return $details;
				}
				else {
					return $response;
				}
			}
		}

		/**
		 * Create a new Rebill Event.
		 *
		 * @param array $values
		 *
		 * @return RebillID
		 */
		public static function createRebillEvent (array $values = array()) {
			// Parse the frequency ($values is by reference)
			$frequency = self::parseFrequencyValue($values);
			if(is_a($frequency, 'RecurringPaymentsResponse')) {
				return $frequency;
			}

			// Merge Defaults and passed values
			$request_array = array_merge(RecurringPaymentsSettings::getDefaultRebillEventFields(), $values);

			// Check for missing fields
			$valid_data = true;
			$fields = array();
			foreach (RecurringPaymentsSettings::getRequiredCreateRebillEvent() as $field_name) {
				if (!array_key_exists($field_name, $request_array) || $request_array[$field_name] == '') {
					$fields[] = $field_name;
					$valid_data = false;
				}
			}

			// The data is invalid, return a `DATA_ERROR`
			if(!$valid_data) {
				return new RecurringPaymentsResponse(array(
					'status' => __('Data error'),
					'response-code' => PGI_Response::DATA_ERROR,
					'response-message' => __('Missing Fields: %s', array(implode(', ', $fields))),
					'missing-fields' => $fields
				));
			}

			$dates = self::parseDates($request_array);
			if(is_a($dates, 'RecurringPaymentsResponse')) {
				return $dates;
			}

			// Build the request
			$eway_request_xml = simplexml_load_string('<CreateRebillEvent xmlns="' . RecurringPaymentsSettings::getSoapNamespace() . '" />');
			foreach($request_array as $field_name => $field_data) {
				$eway_request_xml->addChild($field_name, General::sanitize($field_data));
			}

			// Execute the transaction.
			$ch = Recurring_Request::start(RecurringPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());
			$curl_result = curl_exec($ch);
			$status = curl_getinfo($ch);

			if (curl_errno($ch)) {
				// The Gateway did not connect to eWay successfully or some error occurred.
				return new RecurringPaymentsResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $status,
					'gateway-response' => $curl_result
				), $eway_request_xml);
			}
			else {
				curl_close($ch);

				$response = new RecurringPaymentsResponse($curl_result, $eway_request_xml);
				$xpath = $response->parseGatewayResponse($curl_result);

				// If successful, return the $customerID, otherwise return the $response object
				if($response->isSuccessful()) {
					$rebillID = $xpath->evaluate('string(//eway:RebillID)');
					return $rebillID;
				}
				else {
					return $response;
				}
			}
		}

		/**
		 * Update a Rebill Event.
		 *
		 * @param array $values
		 *
		 * @return boolean
		 */
		public static function updateRebillEvent (array $values = array()) {
			// Parse the frequency ($values is by reference)
			$frequency = self::parseFrequencyValue($values);
			if(is_a($frequency, 'RecurringPaymentsResponse')) {
				return $frequency;
			}

			// Merge Defaults and passed values
			$request_array = array_merge(RecurringPaymentsSettings::getDefaultRebillEventFields(), $values);

			// Check for missing fields
			$valid_data = true;
			$fields = array();
			foreach (RecurringPaymentsSettings::getRequiredUpdateRebillEvent() as $field_name) {
				if (!array_key_exists($field_name, $request_array) || $request_array[$field_name] == '') {
					$fields[] = $field_name;
					$valid_data = false;
				}
			}

			// The data is invalid, return a `DATA_ERROR`
			if(!$valid_data) {
				return new RecurringPaymentsResponse(array(
					'status' => __('Data error'),
					'response-code' => PGI_Response::DATA_ERROR,
					'response-message' => __('Missing Fields: %s', array(implode(', ', $fields))),
					'missing-fields' => $fields
				));
			}

			$dates = self::parseDates($request_array);
			if(is_a($dates, 'RecurringPaymentsResponse')) {
				return $dates;
			}

			// Find out the correct frequency values.
			$frequency = self::getFrequency($values['frequency']);
			$values['RebillInterval'] = $frequency['RebillInterval'];
			$values['RebillIntervalType'] = $frequency['RebillIntervalType'];

			$eway_request_xml = simplexml_load_string('<UpdateRebillEvent xmlns="' . RecurringPaymentsSettings::getSoapNamespace() . '" />');
			foreach($values as $field_name => $field_data) {
				$eway_request_xml->addChild($field_name, General::sanitize($field_data));
			}

			// Execute the transaction.
			$ch = Recurring_Request::start(RecurringPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());

			$curl_result = curl_exec($ch);
			$status = curl_getinfo($ch);

			if (curl_errno($ch)) {
				// The Gateway did not connect to eWay successfully or some error occurred.
				return new RecurringPaymentsResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $status
				), $eway_request_xml);
			}
			else {

				curl_close($ch);

				$response = new RecurringPaymentsResponse($curl_result, $eway_request_xml);
				$xpath = $response->parseGatewayResponse($curl_result);

				// If successful, return the true, otherwise $response object
				if($response->isSuccessful()) {
					return true;
				}
				else {
					return $response;
				}
			}
		}

		/**
		 * Delete existing rebill event.
		 *
		 * @param string $rebillCustomerID
		 * @param string $rebillID
		 *
		 * @return boolean
		 */
		public static function deleteRebillEvent($rebillCustomerID, $rebillID) {
			$params_exist = self::parametersExist($rebillCustomerID, $rebillID);
			if(is_a($params_exist, 'RecurringPaymentsResponse')) {
				return $params_exist;
			}

			$eway_request_xml = simplexml_load_string('<DeleteRebillEvent xmlns="' . RecurringPaymentsSettings::getSoapNamespace() . '" />');
			$eway_request_xml->addChild('RebillCustomerID', General::sanitize($rebillCustomerID));
			$eway_request_xml->addChild('RebillID', General::sanitize($rebillID));

			// Execute the transaction.
			$ch = Recurring_Request::start(RecurringPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());

			$curl_result = curl_exec($ch);
			$status = curl_getinfo($ch);

			if (curl_errno($ch)) {
				// The Gateway did not connect to eWay successfully or some error occurred.
				return new RecurringPaymentsResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $status
				), $eway_request_xml);
			}
			else {

				curl_close($ch);

				$response = new RecurringPaymentsResponse($curl_result, $eway_request_xml);
				$xpath = $response->parseGatewayResponse($curl_result);

				// If successful, return the true, otherwise $response object
				if($response->isSuccessful()) {
					return true;
				}
				else {
					return $response;
				}
			}
		}

		/**
		 * Query an existing rebill event.
		 *
		 * @param string $rebillCustomerID
		 * @param string $rebillID
		 *
		 * @return boolean
		 */
		public static function queryRebillEvent($rebillCustomerID, $rebillID) {
			$params_exist = self::parametersExist($rebillCustomerID, $rebillID);
			if(is_a($params_exist, 'RecurringPaymentsResponse')) {
				return $params_exist;
			}

			$eway_request_xml = simplexml_load_string('<QueryRebillEvent xmlns="' . RecurringPaymentsSettings::getSoapNamespace() . '" />');
			$eway_request_xml->addChild('RebillCustomerID', General::sanitize($rebillCustomerID));
			$eway_request_xml->addChild('RebillID', General::sanitize($rebillID));

			// Execute the transaction.
			$ch = Recurring_Request::start(RecurringPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());

			$curl_result = curl_exec($ch);
			$status = curl_getinfo($ch);

			if (curl_errno($ch)) {
				// The Gateway did not connect to eWay successfully or some error occurred.
				return new RecurringPaymentsResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $status
				), $eway_request_xml);
			}
			else {

				curl_close($ch);

				$response = new RecurringPaymentsResponse($curl_result, $eway_request_xml);
				$xpath = $response->parseGatewayResponse($curl_result);

				// If successful, return the true, otherwise $response object
				if($response->isSuccessful()) {
					$rebillEvent = $xpath->query('//eway:QueryRebillEventResult');

					// Build an array with old/future transactions.
					$details = array();
					foreach($rebillEvent as $node) {
						foreach ($node->childNodes as $child) {
							if(in_array($child->nodeName, array('Result', 'ErrorSeverity', 'ErrorDetails'))) {
								continue;
							}

							$details[$child->nodeName] = $child->nodeValue;
						}
					}

					return $details;
				}
				else {
					return $response;
				}

			}
		}

		/**
		 * Query the next transaction existing rebill event.
		 *
		 * @param string $rebillCustomerID
		 * @param string $rebillID
		 *
		 * @return \RecurringPaymentsResponse|array
		 */
		public static function queryNextTransaction ($rebillCustomerID, $rebillID) {
			$params_exist = self::parametersExist($rebillCustomerID, $rebillID);
			if(is_a($params_exist, 'RecurringPaymentsResponse')) {
				return $params_exist;
			}

			$eway_request_xml = simplexml_load_string('<QueryNextTransaction xmlns="' . RecurringPaymentsSettings::getSoapNamespace() . '" />');
			$eway_request_xml->addChild('RebillCustomerID', General::sanitize($rebillCustomerID));
			$eway_request_xml->addChild('RebillID', General::sanitize($rebillID));

			// Execute the transaction.
			$ch = Recurring_Request::start(RecurringPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());

			$curl_result = curl_exec($ch);
			$status = curl_getinfo($ch);

			if (curl_errno($ch)) {
				// The Gateway did not connect to eWay successfully or some error occurred.
				return new RecurringPaymentsResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $status
				), $eway_request_xml);
			}
			else {

				curl_close($ch);

				$dom = new DOMDocument();
				$response = $curl_result;

				$dom->loadXML($response);
				$xpath = new DOMXPath($dom);

				$xpath->registerNamespace('eway', RecurringPaymentsSettings::getSoapNamespace());
				$xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

				$TransactionDate = $xpath->evaluate('string(//eway:TransactionDate)');
				$CardHolderName = $xpath->evaluate('string(//eway:CardHolderName)');
				$ExpiryDate = $xpath->evaluate('string(//eway:ExpiryDate)');
				$Amount = $xpath->evaluate('string(//eway:Amount)');
				$Status = $xpath->evaluate('string(//eway:Status)');
				$Type = $xpath->evaluate('string(//eway:Type)');

				// Return an array with the next transaction details.
				return array(
					'TransactionDate' => $TransactionDate,
					'CardHolderName' => $CardHolderName,
					'ExpiryDate' => $ExpiryDate,
					'Amount' => $Amount, // in cents
					'Status' => $Status,
					'Type' => $Type
				);
			}
		}

		/**
		 * Query transaction details for specified rebill event. StartDate, EndDate and Status are optional values.
		 *
		 * @param string $rebillCustomerID
		 * @param string $rebillID
		 *
		 * @return \RecurringPaymentsResponse|array
		 */
		public static function queryTransactions ($rebillCustomerID, $rebillID, array $filters = array()) {
			$params_exist = self::parametersExist($rebillCustomerID, $rebillID);
			if(is_a($params_exist, 'RecurringPaymentsResponse')) {
				return $params_exist;
			}

			$eway_request_xml = simplexml_load_string('<QueryTransactions xmlns="' . RecurringPaymentsSettings::getSoapNamespace() . '" />');
			$eway_request_xml->addChild('RebillCustomerID', General::sanitize($rebillCustomerID));
			$eway_request_xml->addChild('RebillID', General::sanitize($rebillID));


			// Do we have extra query strings to add?
			if(isset($filters['startDate']) && $startDate = self::parseDate($filters['startDate'], 'query')) {
				$eway_request_xml->addChild('startDate', $startDate);
			}

			if(isset($filters['endDate']) && $endDate = self::parseDate($filters['endDate'], 'query')) {
				$eway_request_xml->addChild('endDate', $endDate);
			}

			if(isset($filters['status']) && in_array($filters['status'], array('Future', 'Pending', 'Successful', 'Failed'))) {
				$eway_request_xml->addChild('status', $filters['status']);
			}

			// Execute the transaction.
			$ch = Recurring_Request::start(RecurringPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());

			$curl_result = curl_exec($ch);
			$status = curl_getinfo($ch);

			if (curl_errno($ch)) {
				// The Gateway did not connect to eWay successfully or some error occurred.
				return new RecurringPaymentsResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $status
				), $eway_request_xml);
			}
			else {

				curl_close($ch);

				$dom = new DOMDocument();
				$response = $curl_result;

				$dom->loadXML($response);
				$xpath = new DOMXPath($dom);

				$xpath->registerNamespace('eway', RecurringPaymentsSettings::getSoapNamespace());
				$xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

				$QueryTransactionsResult = $xpath->query('//eway:QueryTransactionsResult');

				// Build an array with old/future transactions.
				$queries = array();
				foreach($QueryTransactionsResult as $node) {
					foreach ($node->childNodes as $child) {
						foreach ($child->childNodes as $a) {
							$values[$a->nodeName] = $a->nodeValue;
						}
						array_push($queries, $values);
					}
				}

				// Return an array with the transactions.
				return $queries;
			}
		}

		/**
		 * Parse the dates into the format eWay expects
		 * @see parseDate
		 *
		 * @param array $values
		 *  Passed by reference, RebillInitDate, RebillStartDate and
		 *  RebillEndDate will be updated on success
		 * @return RecurringPaymentsResponse|void
		 *  On fail, RecurringPaymentsResponse is returned, otherwise void.
		 */
		public static function parseDates(array &$values = array()) {
			$valid_data = true;
			$fields = array();

			// Parse dates into an eWay happy format
			if($rebillInitDate = self::parseDate($values['RebillInitDate'])) {
				$values['RebillInitDate'] = $rebillInitDate;
			}
			else {
				$valid_data = false;
				$fields[] = 'RebillInitDate';
			}

			if($rebillStartDate = self::parseDate($values['RebillStartDate'])) {
				$values['RebillStartDate'] = $rebillStartDate;
			}
			else {
				$valid_data = false;
				$fields[] = 'RebillStartDate';
			}

			if($rebillEndDate = self::parseDate($values['RebillEndDate'])) {
				$values['RebillEndDate'] = $rebillEndDate;
			}
			else {
				$valid_data = false;
				$fields[] = 'RebillEndDate';
			}

			// The data is invalid, return a `DATA_ERROR`
			if(!$valid_data) {
				return new RecurringPaymentsResponse(array(
					'status' => __('Data error'),
					'response-code' => PGI_Response::DATA_ERROR,
					'response-message' => __('Invalid date format for: %s', array(implode(', ', $fields))),
					'invalid-fields' => $fields
				));
			}
		}

		/**
		 * Given a string, return a date that eWay will be happy with
		 * @param string $date
		 * @return string|boolean
		 *  A date, formatted day/month/year
		 */
		public static function parseDate($date, $type = 'rebill') {
			$date = strtotime($date);
			$format = ($type === 'rebill') ? 'd/m/Y' : 'Y-m-d';

			if($date) {
				return date($format, $date);
			}
			else {
				return false;
			}
		}

		/**
		 * Parse the frequency from a string to the RebillInterval and
		 * RebillIntervalType.
		 *
		 * @param array $values
		 *  Passed by reference, will be updated with RebillInterval and
		 *  RebillIntervalType keys and 'frequency' key will be removed
		 *  on success.
		 * @return RecurringPaymentsResponse|void
		 *  On fail, RecurringPaymentsResponse is returned, otherwise void.
		 */
		public static function parseFrequencyValue(array &$values = array()) {
			// Parse the frequency
			$frequency = self::getFrequency($values['frequency']);
			if(count($frequency) == 0) {
				return new RecurringPaymentsResponse(array(
					'status' => __('Data error'),
					'response-code' => PGI_Response::DATA_ERROR,
					'response-message' => __('Invalid frequency: %s. Supports: weekly, fortnightly, monthly or yearly.', array($values['frequency']))
				));
			}
			else {
				$values['RebillInterval'] = $frequency['RebillInterval'];
				$values['RebillIntervalType'] = $frequency['RebillIntervalType'];
				unset($values['frequency']);
			}
		}

		/**
		 * Setup the correct fields for eWay.
		 *
		 * @param string $type
		 *	Type of payment: Weekly, Fortnightly, Monthly or Yearly.
		 * @return array
		 */
		public static function getFrequency($type) {
			$data = array();

			switch (strtolower($type)) {
				case "weekly":
					$data['RebillInterval'] = 1;
					$data['RebillIntervalType'] = RecurringPaymentsSettings::WEEK;
					break;
				case "fortnightly":
					$data['RebillInterval'] = 2;
					$data['RebillIntervalType'] = RecurringPaymentsSettings::WEEK;
					break;
				case "monthly":
					$data['RebillInterval'] = 1;
					$data['RebillIntervalType'] = RecurringPaymentsSettings::MONTH;
					break;
				case "yearly":
					$data['RebillInterval'] = 1;
					$data['RebillIntervalType'] = RecurringPaymentsSettings::YEAR;
					break;
			}

			return $data;
		}

		/**
		 * Builds a response is the given parameters are not filled
		 *
		 * @return RecurringPaymentsResponse|boolean
		 */
		public static function parametersExist($rebillCustomerID, $rebillID = null) {
			$params = array();
			if (!$rebillCustomerID) {
				$params[] = 'rebillCustomerID';
			}

			if (!$rebillID && !is_null($rebillID)) {
				$params[] = 'rebillID';
			}

			if(!empty($params)) {
				return new RecurringPaymentsResponse(array(
					'status' => __('Data error'),
					'response-code' => PGI_Response::DATA_ERROR,
					'response-message' => __('Missing parameters: %s', array(implode(', ', $params))),
					'missing-fields' => $params
				));
			}
			else {
				return true;
			}
		}
	}

	Abstract Class Recurring_Request extends SOAP_Request {
		public static function start($uri, $xml, $timeout = 60) {
			self::$namespace = RecurringPaymentsSettings::getSoapNamespace();
			return parent::start($uri, $xml, $timeout);
		}
	}

	Class RecurringPaymentsResponse extends eWayResponse {

		/**
		 * Given the eWay Gateway response for a Token Payment, return
		 * XPath so that we can get the values out of it
		 *
		 * @param string $response
		 * @return DOMXPath
		 */
		public function parseGatewayResponse($response) {
			// Create a document for the result and load the result
			$eway_result = new DOMDocument('1.0', 'utf-8');
			$eway_result->formatOutput = true;
			$eway_result->loadXML($response);
			$this->xpath = new DOMXPath($eway_result);

			// Register SOAP namespaces:
			$this->xpath->registerNamespace('eway', RecurringPaymentsSettings::getSoapNamespace());
			$this->xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

			return $this->xpath;
		}

		public function parseResponse($response) {
			$this->xpath = $this->parseGatewayResponse($response);

			// Generate status result:
			$result = $this->xpath->evaluate('string(//eway:Result)');
			$eway_approved = 'Success' == $result;

			$return = array(
				'status' => $eway_approved ?  __('Success') : __('Fail'),
			);

			if(!$eway_approved) {
				// Error from the API.
				$error = $this->xpath->evaluate('string(//eway:ErrorDetails)');
				$return['response-message'] = $error;
				$return['response-code'] = PGI_Response::GATEWAY_ERROR;
			}

			return $return;
		}

	/*-------------------------------------------------------------------------
		Utilities
	-------------------------------------------------------------------------*/

		public function isSuccessful() {
			return is_array($this->response)
				&& array_key_exists('status', $this->response)
				&& $this->response['status'] == __('Success');
		}

	}
