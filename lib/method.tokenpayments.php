<?php

    require_once EXTENSIONS . '/eway/lib/class.api.php';

    Class TokenPaymentsSettings extends eWaySettings {

        public static function getGatewayURI() {
            return (eWayAPI::isTesting())
                ? 'https://www.eway.com.au/gateway/ManagedPaymentService/test/managedcreditcardpayment.asmx'
                : 'https://www.eway.com.au/gateway/ManagedPaymentService/managedcreditcardpayment.asmx';
        }

        public static function getDefaults() {}

        public static function getRequiredFields() {}

        /**
         * Required fields to create a rebill customer.
         * @return type
         */
        public static function getRequiredCreateCustomer() {
            return array(
                'Title',
                'FirstName',
                'LastName',
                'Country',
                'CCNumber',
                'CCExpiryMonth',
                'CCExpiryYear',
                'CVN'
            );
        }

        /**
         * Required fields to update customer.
         * @return type
         */
        public static function getRequiredUpdateCustomer() {
            return array(
                'managedCustomerID',
                'Title',
                'FirstName',
                'LastName',
                'Country',
                'CCNumber',
                'CCExpiryMonth',
                'CCExpiryYear',
                'CVN'
            );
        }

        /**
         * Required fields to process the Token payment.
         * @return type
         */
        public static function getRequiredTokenPayment() {
            return array(
                'managedCustomerID',
                'amount',
                'invoiceReference',
                'invoiceDescription'
            );
        }

        /**
         * Required fields to Query the Customer.
         * @return type
         */
        public static function getRequiredQueryCustomer() {
            return array(
                'managedCustomerID'
            );
        }

        /**
         * Required fields to process payments usign CVN.
         * @return type
         */
        public static function getRequiredCVNPayment() {
            return array(
                'managedCustomerID',
                'amount',
                'invoiceReference',
                'invoiceDescription',
                'cvn'
            );
        }

    }

    Class TokenPayments extends Token_Request {

        /**
         * Create a new Customer.
         *
         * @param array $values Array of values containing the Customer's details to create a new customer.
         *
         * @return CustomerID
         */
        public static function createCustomer(array $values = array()) {

            // Check for missing fields
            $valid_data = true;
            $missing_fields = array();
            $error = null;
            foreach (TokenPaymentsSettings::getRequiredCreateCustomer() as $field_name) {
                if (!array_key_exists($field_name, $values) || $values[$field_name] == '') {
                    $missing_fields[] = $field_name;
                    $valid_data = false;
                }
            }

            // The data is invalid, return a `DATA_ERROR`
            if(!$valid_data) {
                return new TokenPaymentsResponse(array(
                    'status' => __('Data error'),
                    'response-code' => PGI_Response::DATA_ERROR,
                    'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
                    'missing-fields' => $missing_fields
				));
            }

            $eway_request_xml = simplexml_load_string('<CreateCustomer xmlns="https://www.eway.com.au/gateway/managedpayment" />');
            foreach($values as $field_name => $field_data) {
                $eway_request_xml->addChild($field_name, General::sanitize($field_data));
            }

            $ch = Token_Request::start(TokenPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());
            $curl_result = curl_exec($ch);
            $status = curl_getinfo($ch);

            if (curl_errno($ch)) {
                // The Gateway did not connect to eWay successfully or some error occurred.
                return new TokenPaymentsResponse(array(
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

				// Register SOAP namespaces:
                $xpath->registerNamespace('eway', 'https://www.eway.com.au/gateway/managedpayment');
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                $customerId = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:CreateCustomerResult)');

                // Return a new Customer id.
                if ($customerId) {
                    return $customerId;
                }
				else {
                    return new TokenPaymentsResponse($curl_result, $eway_request_xml);
                }
            }
        }

        /**
        * Update Customer.
         *
         * @param array $values Array of values containing the Customer's details to update the customer.
         *
         * @return boolean
         */
        public static function updateCustomer(array $values = array()) {

            // Check for missing fields
            $valid_data = true;
            $missing_fields = array();
            $error = null;
            foreach (TokenPaymentsSettings::getRequiredUpdateCustomer() as $field_name) {
                if (!array_key_exists($field_name, $values) || $values[$field_name] == '') {
                    $missing_fields[] = $field_name;
                    $valid_data = false;
                }
            }

            // The data is invalid, return a `DATA_ERROR`
            if(!$valid_data) {
                return new TokenPaymentsResponse(array(
                    'status' => __('Data error'),
                    'response-code' => PGI_Response::DATA_ERROR,
                    'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
                    'missing-fields' => $missing_fields
				));
            }

            $eway_request_xml = simplexml_load_string('<UpdateCustomer xmlns="https://www.eway.com.au/gateway/managedpayment" />');
            foreach($values as $field_name => $field_data) {
                $eway_request_xml->addChild($field_name, General::sanitize($field_data));
            }

            $ch = Token_Request::start(TokenPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());
            $curl_result = curl_exec($ch);
            $status = curl_getinfo($ch);

            if (curl_errno($ch)) {
                // The Gateway did not connect to eWay successfully or some error occurred.
                return new TokenPaymentsResponse(array(
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

				// Register SOAP namespaces:
                $xpath->registerNamespace('eway', 'https://www.eway.com.au/gateway/managedpayment');
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                $response = (boolean)$xpath->evaluate('string(/soap:Envelope/soap:Body//eway:UpdateCustomerResult)');

                // Return a new Customer id.
                if ($response === true) {
                    return true;
                }
				else {
                    return new TokenPaymentsResponse($curl_result, $eway_request_xml);
                }
            }
        }

        /**
         * Query managed customer details.
         *
         * @param array $values Array of values containing the payment details.
         *
         * @return boolean
         */
        public static function queryTokenCustomer(array $values = array()) {

            // Check for missing fields
            $valid_data = true;
            $missing_fields = array();
            $error = null;
            foreach (TokenPaymentsSettings::getRequiredQueryCustomer() as $field_name) {
                if (!array_key_exists($field_name, $values) || $values[$field_name] == '') {
                    $missing_fields[] = $field_name;
                    $valid_data = false;
                }
            }

            // The data is invalid, return a `DATA_ERROR`
            if(!$valid_data) {
                return new TokenPaymentsResponse(array(
                    'status' => __('Data error'),
                    'response-code' => PGI_Response::DATA_ERROR,
                    'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
                    'missing-fields' => $missing_fields
				));
            }

            $eway_request_xml = simplexml_load_string('<QueryCustomer xmlns="https://www.eway.com.au/gateway/managedpayment" />');
            foreach($values as $field_name => $field_data) {
                $eway_request_xml->addChild($field_name, General::sanitize($field_data));
            }

            $ch = Token_Request::start(TokenPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());
            $curl_result = curl_exec($ch);
            $status = curl_getinfo($ch);

            if (curl_errno($ch)) {
                // The Gateway did not connect to eWay successfully or some error occurred.
                return new TokenPaymentsResponse(array(
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

				// Register namespaces
                $xpath->registerNamespace('eway', 'https://www.eway.com.au/gateway/managedpayment');
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                $responseCCName = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:CCName)');
                $responseCCNumber = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:CCNumber)');
                $responseCCExpiryMonth = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:CCExpiryMonth)');
                $responseCCExpiryYear = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:CCExpiryYear)');

                $response = array(
                    'responseCCName' => $responseCCName,
                    'responseCCNumber' => $responseCCNumber,
                    'responseCCExpiryMonth' => $responseCCExpiryMonth,
                    'responseCCExpiryYear' => $responseCCExpiryYear,
                );

                // Return a new Customer id.
                if ($response['responseCCNumber']) {
                    return $response;
                }
				else {
                    return new TokenPaymentsResponse(array(
                        'status' => __('Gateway error'),
                        'response-code' => PGI_Response::GATEWAY_ERROR,
                        'response-message' => __('There was an error querying Customer.'),
                        'curl-info' => $status,
						'gateway-response' => $curl_result
                    ), $eway_request_xml);
                }
            }
        }

        /**
        * Process Payment.
         *
         * @param array $values Array of values containing the payment details.
         *
         * @return boolean
         */
        public static function processTokenPayment(array $values = array()) {

            // Check for missing fields
            $valid_data = true;
            $missing_fields = array();
            $error = null;
            foreach (TokenPaymentsSettings::getRequiredTokenPayment() as $field_name) {
                if (!array_key_exists($field_name, $values) || $values[$field_name] == '') {
                    $missing_fields[] = $field_name;
                    $valid_data = false;
                }
            }

            // The data is invalid, return a `DATA_ERROR`
            if(!$valid_data) {
                return new TokenPaymentsResponse(array(
                    'status' => __('Data error'),
                    'response-code' => PGI_Response::DATA_ERROR,
                    'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
                    'missing-fields' => $missing_fields
				));
            }

            $eway_request_xml = simplexml_load_string('<ProcessPayment xmlns="https://www.eway.com.au/gateway/managedpayment" />');
            foreach($values as $field_name => $field_data) {
                $eway_request_xml->addChild($field_name, General::sanitize($field_data));
            }

            $ch = Token_Request::start(TokenPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());
            $curl_result = curl_exec($ch);
            $status = curl_getinfo($ch);

            if (curl_errno($ch)) {
                // The Gateway did not connect to eWay successfully or some error occurred.
                return new TokenPaymentsResponse(array(
                    'status' => __('Gateway error'),
                    'response-code' => PGI_Response::GATEWAY_ERROR,
                    'response-message' => __('There was an error connecting to eWay.'),
                    'curl-info' => $status
                ), $eway_request_xml);
            }
            else {
				curl_close($ch);
				return new TokenPaymentsResponse($curl_result, $eway_request_xml);
            }
        }

		/**
		 * Process payment with CVN.
		 *
		 * @param array $values Array of values containing the payment details.
		 *
		 * @return boolean
		 */
		public static function ProcessPaymentWithCVN(array $values = array()) {

			// Check for missing fields
			$valid_data = true;
			$missing_fields = array();
			$error = null;
			foreach (TokenPaymentsSettings::getRequiredCVNPayment() as $field_name) {
				if (!array_key_exists($field_name, $values) || $values[$field_name] == '') {
					$missing_fields[] = $field_name;
					$valid_data = false;
				}
			}

			// The data is invalid, return a `DATA_ERROR`
			if(!$valid_data) {
				return new TokenPaymentsResponse(array(
					'status' => __('Data error'),
					'response-code' => PGI_Response::DATA_ERROR,
					'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
					'missing-fields' => $missing_fields
				));
			}

			$eway_request_xml = simplexml_load_string('<ProcessPaymentWithCVN xmlns="https://www.eway.com.au/gateway/managedpayment" />');
			foreach($values as $field_name => $field_data) {
				$eway_request_xml->addChild($field_name, General::sanitize($field_data));
			}

			$ch = Token_Request::start(TokenPaymentsSettings::getGatewayURI(), $eway_request_xml->asXML());
			$curl_result = curl_exec($ch);
			$status = curl_getinfo($ch);

			if (curl_errno($ch)) {
				// The Gateway did not connect to eWay successfully or some error occurred.
				return new TokenPaymentsResponse(array(
					'status' => __('Gateway error'),
					'response-code' => PGI_Response::GATEWAY_ERROR,
					'response-message' => __('There was an error connecting to eWay.'),
					'curl-info' => $status
				), $eway_request_xml);
			}
			else {
				curl_close($ch);
				return new TokenPaymentsResponse($curl_result, $eway_request_xml);
			}
		}
	}

	Abstract Class Token_Request extends PGI_Request {
		public static function start($uri, $xml, $timeout = 60) {

			// Make sure there isn't a xml tag in the body.
			$xml_body = str_replace('<?xml version="1.0"?>', '', $xml);

			$data = '<?xml version="1.0" encoding="utf-8"?>
                    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                      <soap:Header>
                            <eWAYHeader xmlns="https://www.eway.com.au/gateway/managedpayment">
                              <eWAYCustomerID>%1$s</eWAYCustomerID>
                              <Username>%2$s</Username>
                              <Password>%3$s</Password>
                            </eWAYHeader>
                      </soap:Header>
                      <soap:Body>%4$s</soap:Body>
                    </soap:Envelope>';

			// Prepare data to be sent to eWay.
			$post_xml = sprintf($data,
				eWaySettings::getCustomerId(),
				eWaySettings::getMerchantId(),
				eWaySettings::getMerchantPassword(),
				$xml_body
			);

			$header  = "POST ". $uri ." HTTP/1.1 \r\n";
			$header .= "Host: www.eway.com.au \r\n";
			$header .= "Content-Type: text/xml; charset=utf-8 \r\n";
			$header .= "Content-Length: ".strlen($post_xml)." \r\n";
			$header .= "Connection: close \r\n\r\n";
			$header .= $post_xml;

			// Send the data to eWay.
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HEADER, false);

			curl_setopt($curl, CURLOPT_URL, $uri);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $header);

			return $curl;
		}
	}

	Class TokenPaymentsResponse extends eWayResponse {

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
			$this->xpath->registerNamespace('eway', 'https://www.eway.com.au/gateway/managedpayment');
			$this->xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

			return $this->xpath;
		}

		public function parseResponse($response) {
			$xpath = $this->parseGatewayResponse($response);

			// eWay responses come back like 00,Transaction Approved(Test CVN Gateway)
			// It's important to known that although it's called ewayTrxnError, Error's can be 'good' as well
			$ewayTrxnError = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayTrxnError)');
            $eway_return = explode(',', $ewayTrxnError, 2);

			// Get the code
			$eway_code = is_numeric($eway_return[0]) ? array_shift($eway_return) : '';

			// Do we have an error code? If there is a SOAP execption thrown, we will not
			// so will have to parse the error code from the strings.
			if($eway_code === '') {
				$fault_error = $xpath->evaluate('string(/soap:Envelope/soap:Body//faultstring)');

				if(preg_match('/Credit Card expiry date must be valid/i', $fault_error)) {
					$eway_code = 54;
					$eway_response = $fault_error;
				}
				else if(preg_match('/The \'CCNumber\' element is invalid/i', $fault_error)) {
					$eway_code = 14;
					$eway_response = 'Credit Card number must be valid';
				}
			}
			// Normal request handled by eway.
			else {
				// Generate status result:
				$eway_transaction_id   = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayTrxnNumber)');
				$bank_authorisation_id = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayAuthCode)');

				// Get the response
				$eway_response = preg_replace('/^eWAY Error:\s*/i', '', array_shift($eway_return));

				// Get the return Amount
				$ewayReturnAmount = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayReturnAmount)');
			}

			$return = array(
				'status' => in_array($eway_code, eWaySettings::getApprovedCodes()) ? __('Approved') : __('Declined'),
				'response-code' => $eway_code,
				'response-message' => $eway_response
			);

			if(isset($eway_transaction_id)) {
				$return['pgi-transaction-id'] = $eway_transaction_id;
			}

			if(isset($bank_authorisation_id)) {
				$return['bank-authorisation-id'] = $bank_authorisation_id;
			}

			if(isset($ewayReturnAmount)) {
				$return['amount'] = $ewayReturnAmount;
			}

			return $return;
		}

		public function addToEventXML(XMLElement $event_xml) {
			$xEway = new XMLElement('eway');
			$xEway->appendChild(
				new XMLElement('message', $this->getResponseMessage())
			);

			if($this->isSuccessful() === false) {
				$event_xml->setAttribute('result', 'error');

				// Get the raw response from eWay
				$response = $this->getResponse(false);
				$xpath = $this->parseGatewayResponse($response);

				// Get the fault reason
				$fault_error = $xpath->evaluate('string(/soap:Envelope/soap:Body//faultstring)');
				$xEway->appendChild(new XMLElement('fault-string', $fault_error));
			}

			else {
				foreach($this->getSuccess() as $key => $value) {
					$xEway->appendChild(
						new XMLElement($key, $value)
					);
				}
			}

			$event_xml->appendChild($xEway);
		}

	}