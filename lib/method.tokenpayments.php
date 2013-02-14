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
                return array(
                    'status' => __('Data error'),
                    'response-code' => PGI_Response::DATA_ERROR,
                    'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
                    'missing-fields' => $missing_fields
                );
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
                return array(
                    'status' => __('Gateway error'),
                    'response-code' => PGI_Response::GATEWAY_ERROR,
                    'response-message' => __('There was an error connecting to eWay.'),
                    'curl-info' => $info
                );
            } 
            else {
                
                curl_close($ch);

                $dom = new DOMDocument();
                $response = $curl_result;

                $dom->loadXML($response);
                $xpath = new DOMXPath($dom);

                $xpath->registerNamespace('eway', 'https://www.eway.com.au/gateway/managedpayment');

                // Also give the SOAP namespace a name:
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                $customerId = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:CreateCustomerResult)');
                
                // Return a new Customer id.
                if ($customerId) {
                    return $customerId;
                } else{
                    return array(
                        'status' => __('Gateway error'),
                        'response-code' => PGI_Response::GATEWAY_ERROR,
                        'response-message' => __('There was an error creating a new Customer.'),
                        'curl-info' => $status
                    );
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
                return array(
                    'status' => __('Data error'),
                    'response-code' => PGI_Response::DATA_ERROR,
                    'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
                    'missing-fields' => $missing_fields
                );
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
                return array(
                    'status' => __('Gateway error'),
                    'response-code' => PGI_Response::GATEWAY_ERROR,
                    'response-message' => __('There was an error connecting to eWay.'),
                    'curl-info' => $info
                );
            } 
            else {
                
                curl_close($ch);

                $dom = new DOMDocument();
                $response = $curl_result;

                $dom->loadXML($response);
                $xpath = new DOMXPath($dom);

                $xpath->registerNamespace('eway', 'https://www.eway.com.au/gateway/managedpayment');

                // Also give the SOAP namespace a name:
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                $response = (boolean)$xpath->evaluate('string(/soap:Envelope/soap:Body//eway:UpdateCustomerResult)');
                
                // Return a new Customer id.
                if ($response === true) {
                    return true;
                } else{
                    return array(
                        'status' => __('Gateway error'),
                        'response-code' => PGI_Response::GATEWAY_ERROR,
                        'response-message' => __('There was an error updating Customer.'),
                        'curl-info' => $status
                    );
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
                return array(
                    'status' => __('Data error'),
                    'response-code' => PGI_Response::DATA_ERROR,
                    'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
                    'missing-fields' => $missing_fields
                );
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
                return array(
                    'status' => __('Gateway error'),
                    'response-code' => PGI_Response::GATEWAY_ERROR,
                    'response-message' => __('There was an error connecting to eWay.'),
                    'curl-info' => $info
                );
            } 
            else {
                
                curl_close($ch);

                $dom = new DOMDocument();
                $response = $curl_result;

                $dom->loadXML($response);
                $xpath = new DOMXPath($dom);

                $xpath->registerNamespace('eway', 'https://www.eway.com.au/gateway/managedpayment');

                // Also give the SOAP namespace a name:
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                $ewayTrxnError = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayTrxnError)');
                $ewayTrxnStatus = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayTrxnStatus)');
                $ewayReturnAmount = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayReturnAmount)');                
                $eway_return = explode(',', $ewayTrxnError, 2);
                $eway_code = is_numeric($eway_return[0]) ? array_shift($eway_return) : '';
                $eway_response = preg_replace('/^eWAY Error:\s*/i', '', array_shift($eway_return));               
                $eway_transaction_id   = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayTrxnNumber)');
                $bank_authorisation_id = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayAuthCode)');

                $response = array(
                    'status' => in_array($eway_code, eWaySettings::getApprovedCodes()) ? __('Approved') : __('Declined'),
                    'response-code' => $eway_code,
                    'response-message' => $eway_response,
                    'pgi-transaction-id' => $eway_transaction_id,
                    'bank-authorisation-id' => $bank_authorisation_id,
                    'amount' => $ewayReturnAmount
                );

                // Return response.
                if ($response['status'] === 'Approved') {
                    return $response;
                } else{
                    return array(
                        'status' => __('Gateway error'),
                        'response-code' => PGI_Response::GATEWAY_ERROR,
                        'response-message' => __('There was an error processing payment.'),
                        'curl-info' => $status
                    );
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
                return array(
                    'status' => __('Data error'),
                    'response-code' => PGI_Response::DATA_ERROR,
                    'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
                    'missing-fields' => $missing_fields
                );
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
                return array(
                    'status' => __('Gateway error'),
                    'response-code' => PGI_Response::GATEWAY_ERROR,
                    'response-message' => __('There was an error connecting to eWay.'),
                    'curl-info' => $info
                );
            } 
            else {
                
                curl_close($ch);

                $dom = new DOMDocument();
                $response = $curl_result;

                $dom->loadXML($response);
                $xpath = new DOMXPath($dom);

                $xpath->registerNamespace('eway', 'https://www.eway.com.au/gateway/managedpayment');

                // Also give the SOAP namespace a name:
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
                } else{
                    return array(
                        'status' => __('Gateway error'),
                        'response-code' => PGI_Response::GATEWAY_ERROR,
                        'response-message' => __('There was an error querying Customer.'),
                        'curl-info' => $status
                    );
                }                    
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
                return array(
                    'status' => __('Data error'),
                    'response-code' => PGI_Response::DATA_ERROR,
                    'response-message' => __('Missing Fields: %s', array(implode(', ', $missing_fields))),
                    'missing-fields' => $missing_fields
                );
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
                return array(
                    'status' => __('Gateway error'),
                    'response-code' => PGI_Response::GATEWAY_ERROR,
                    'response-message' => __('There was an error connecting to eWay.'),
                    'curl-info' => $info
                );
            } 
            else {
                
                curl_close($ch);

                $dom = new DOMDocument();
                $response = $curl_result;

                $dom->loadXML($response);
                $xpath = new DOMXPath($dom);

                $xpath->registerNamespace('eway', 'https://www.eway.com.au/gateway/managedpayment');

                // Also give the SOAP namespace a name:
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                $ewayTrxnError = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayTrxnError)');
                $ewayTrxnStatus = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayTrxnStatus)');
                $ewayReturnAmount = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayReturnAmount)');                
                $eway_return = explode(',', $ewayTrxnError, 2);
                $eway_code = is_numeric($eway_return[0]) ? array_shift($eway_return) : '';
                $eway_response = preg_replace('/^eWAY Error:\s*/i', '', array_shift($eway_return));               
                $eway_transaction_id   = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayTrxnNumber)');
                $bank_authorisation_id = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ewayAuthCode)');

                $response = array(
                    'status' => in_array($eway_code, eWaySettings::getApprovedCodes()) ? __('Approved') : __('Declined'),
                    'response-code' => $eway_code,
                    'response-message' => $eway_response,
                    'pgi-transaction-id' => $eway_transaction_id,
                    'bank-authorisation-id' => $bank_authorisation_id,
                    'amount' => $ewayReturnAmount
                );
               
                // Return response.
                if ($response['status'] === 'Approved') {
                    return $response;
                } else{
                    return array(
                        'status' => __('Gateway error'),
                        'response-code' => PGI_Response::GATEWAY_ERROR,
                        'response-message' => __('There was an error querying Customer.'),
                        'curl-info' => $status
                    );
                }                    
            }
        }
        
    }