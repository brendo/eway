<?php

	require_once EXTENSIONS . '/eway/lib/class.api.php';

	Class RecurringPaymentsSettings extends eWaySettings {

		public static function getGatewayURI() {
			return (eWayAPI::isTesting())
				? 'https://www.eway.com.au/gateway/rebill/test/managerebill_test.asmx'
				: 'https://www.eway.com.au/gateway/rebill/upload.aspx';
		}

        public static function getDefaults() {}

        public static function getRequiredFields() {}
        
        /**
         * Required fields to create a rebill customer.
         * @return type
         */
        public static function getRequiredCreateRebillCustomer() {
			return array(
				'customerTitle',
				'customerFirstName',
				'customerLastName',
				'customerEmail',
				'customerURL'
			);
        }
       
        /**
         * Required fields to create a rebill event.
         * @return type
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
         * @return type
         */
        public static function getRequiredUpdateRebillEvent() {
			return array(
                'RebillCustomerID',	
                'RebillID',	
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
        
	}

	Class RecurringPayments extends Recurring_Request {
       
        /**
         * Create a new Rebill Customer.
         * 
         * @param array $values Array of values containing the Customer's details to create a new rebill customer.
         * 
         * @return RebillCustomerID
         */
        public static function createRebillCustomer(array $values = array()) {
            
            // Check for missing fields
            $valid_data = true;
            $missing_fields = array();
            $error = null;                
            foreach (RecurringPaymentsSettings::getRequiredCreateRebillCustomer() as $field_name) {
                if (!array_key_exists($field_name, $values) || $values[$field_name] == '') {
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

			$eway_request_xml = simplexml_load_string('<CreateRebillCustomer xmlns="http://www.eway.com.au/gateway/rebill/manageRebill" />');
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
                    'curl-info' => $info
                ), $eway_request_xml);
            } 
            else {
                
                curl_close($ch);

                $dom = new DOMDocument();
                $response = $curl_result;

                $dom->loadXML($response);
                $xpath = new DOMXPath($dom);

                // Because 'CreateRebillCustomerResponse' has another namespace,
                // we need to define how we'll name it in our XPath expression:
                $xpath->registerNamespace('eway', 'http://www.eway.com.au/gateway/rebill/manageRebill');

                // Also give the SOAP namespace a name:
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                $customerId = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:RebillCustomerID)');
                $result = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:Result)');
                
                // Return a new rebill customer id.
                if ($result == "Success") {
                    return $customerId;
                } else{
                    return new RecurringPaymentsResponse(array(
                        'status' => __('Gateway error'),
                        'response-code' => PGI_Response::GATEWAY_ERROR,
                        'response-message' => __('There was an error creating a new reBill customer.'),
                        'curl-info' => $status
                    ), $response);
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

            // Find out the correct frequency values.
            $frequency = self::getFrequency($values['frequency']);
            $values['RebillInterval'] = $frequency['RebillInterval'];
            $values['RebillIntervalType'] = $frequency['RebillIntervalType'];
            
            // Check for missing fields
            $valid_data = true;
            $missing_fields = array();
            $error = null;                
            foreach (RecurringPaymentsSettings::getRequiredCreateRebillEvent() as $field_name) {
                if (!array_key_exists($field_name, $values) || $values[$field_name] == '') {
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
            
			$eway_request_xml = simplexml_load_string('<CreateRebillEvent xmlns="http://www.eway.com.au/gateway/rebill/manageRebill" />');
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
                    'curl-info' => $info
                ), $eway_request_xml);
            } 
            else {
                
                curl_close($ch);

                $dom = new DOMDocument();
                $response = $curl_result;

                $dom->loadXML($response);
                $xpath = new DOMXPath($dom);

                // Because 'CreateRebillCustomerResponse' has another namespace,
                // we need to define how we'll name it in our XPath expression:
                $xpath->registerNamespace('eway', 'http://www.eway.com.au/gateway/rebill/manageRebill');

                // Also give the SOAP namespace a name:
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                $rebillID = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:RebillID)');
                $result = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:Result)');
                
                // Return a new rebill customer id.
                if ($result == "Success") {
                    return $rebillID;
                } else{
                    return new RecurringPaymentsResponse(array(
                        'status' => __('Gateway error'),
                        'response-code' => PGI_Response::GATEWAY_ERROR,
                        'response-message' => __('There was an error creating a new reBill event.'),
                        'curl-info' => $status
                    ), $response);
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
            
            // Find out the correct frequency values.
            $frequency = self::getFrequency($values['frequency']);
            $values['RebillInterval'] = $frequency['RebillInterval'];
            $values['RebillIntervalType'] = $frequency['RebillIntervalType'];            
            
            // Check for missing fields
            $valid_data = true;
            $missing_fields = array();
            $error = null;                
            foreach (RecurringPaymentsSettings::getRequiredUpdateRebillEvent() as $field_name) {
                if (!array_key_exists($field_name, $values) || $values[$field_name] == '') {
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
            
			$eway_request_xml = simplexml_load_string('<UpdateRebillEvent xmlns="http://www.eway.com.au/gateway/rebill/manageRebill" />');
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
                    'curl-info' => $info
                ), $eway_request_xml);
            } 
            else {
                
                curl_close($ch);

                $dom = new DOMDocument();
                $response = $curl_result;

                $dom->loadXML($response);
                $xpath = new DOMXPath($dom);

                $xpath->registerNamespace('eway', 'http://www.eway.com.au/gateway/rebill/manageRebill');
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
                $result = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:Result)');
                
                // Return a new rebill customer id.
                if ($result == "Success") {
                    return true;
                } else{
                    // Error from the API.
                    $error = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ErrorDetails)');                    
                    return new RecurringPaymentsResponse(array(
                        'status' => __('Gateway error'),
                        'response-code' => PGI_Response::GATEWAY_ERROR,
                        'response-message' => $error,
                        'curl-info' => $status
                    ), $response);
                }                    
            }           
        } 

        /**
         * Delete existing rebill event.
         * 
         * @param type $rebillCustomerID
         * @param type $rebillID
         * 
         * @return boolean
         */
        public static function deleteRebillEvent($rebillCustomerID, $rebillID) {
            
            if (!$rebillCustomerID) return;
            if (!$rebillID)         return;
            
            $eway_request_xml = simplexml_load_string('<DeleteRebillEvent xmlns="http://www.eway.com.au/gateway/rebill/manageRebill" />');
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
                    'curl-info' => $info
                ), $eway_request_xml);
            } 
            else {
                
                curl_close($ch);

                $dom = new DOMDocument();
                $response = $curl_result;

                $dom->loadXML($response);
                $xpath = new DOMXPath($dom);

                $xpath->registerNamespace('eway', 'http://www.eway.com.au/gateway/rebill/manageRebill');
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
                $result = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:Result)');
                
                // Return a boolean in case of success.
                if ($result == "Success") {
                    return true;
                } else{
                    return new RecurringPaymentsResponse(array(
                        'status' => __('Gateway error'),
                        'response-code' => PGI_Response::GATEWAY_ERROR,
                        'response-message' => __('There was an error updating a reBill event.'),
                        'curl-info' => $status
                    ), $response);
                }  

            }  
        }          
        
        /**
         * Query transaction details for specified rebill event. StartDate, EndDate and Status are optional values.
         * 
         * @param type $rebillCustomerID
         * @param type $rebillID         
         * 
         * @return \RecurringPaymentsResponse|array
         */        
        public static function queryTransactions ($rebillCustomerID, $rebillID) {            

            if (!$rebillCustomerID) return;
            if (!$rebillID)         return;
            
			$eway_request_xml = simplexml_load_string('<QueryTransactions xmlns="http://www.eway.com.au/gateway/rebill/manageRebill" />');
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
                    'curl-info' => $info
                ), $eway_request_xml);
            } 
            else {
                
                curl_close($ch);

                $dom = new DOMDocument();
                $response = $curl_result;

                $dom->loadXML($response);
                $xpath = new DOMXPath($dom);

                $xpath->registerNamespace('eway', 'http://www.eway.com.au/gateway/rebill/manageRebill');
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                $QueryTransactionsResult = $xpath->query('/soap:Envelope/soap:Body//eway:QueryTransactionsResult');
                
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
         * Query the next transaction existing rebill event.
         * 
         * @param type $rebillCustomerID
         * @param type $rebillID
         * 
         * @return array
         */
        public static function queryNextTransaction ($rebillCustomerID, $rebillID) {
            
            if (!$rebillCustomerID) return;
            if (!$rebillID)         return;
            
			$eway_request_xml = simplexml_load_string('<QueryNextTransaction xmlns="http://www.eway.com.au/gateway/rebill/manageRebill" />');
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
                    'curl-info' => $info
                ), $eway_request_xml);
            } 
            else {
                
                curl_close($ch);

                $dom = new DOMDocument();
                $response = $curl_result;

                $dom->loadXML($response);
                $xpath = new DOMXPath($dom);

                $xpath->registerNamespace('eway', 'http://www.eway.com.au/gateway/rebill/manageRebill');
                $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

                $TransactionDate = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:TransactionDate)');
                $CardHolderName = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:CardHolderName)');
                $ExpiryDate = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:ExpiryDate)');
                $Amount = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:Amount)');
                $Status = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:Status)');
                $Type = $xpath->evaluate('string(/soap:Envelope/soap:Body//eway:Type)');
                
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
         * Find out type of payment frequency for eWay.
         * 
         * @param int $type Type of payment:  1 => Weekly,  2 => Fortnightly, 3 => Montly and 4 => Yearly
         * @return array
         */
        public static function getFrequency($type) {
            $data = array();
            switch ($type) {
                case 1: // Weekly
                    $data['RebillInterval'] = 1;
                    $data['RebillIntervalType'] = 2;
                    break;
                case 2: // Fortnighly
                    $data['RebillInterval'] = 2;
                    $data['RebillIntervalType'] = 2;
                    break;
                case 3: // Montly
                    $data['RebillInterval'] = 1;
                    $data['RebillIntervalType'] = 3;
                    break;
                case 4: // Yearly
                    $data['RebillInterval'] = 1;
                    $data['RebillIntervalType'] = 4;
                    break;
            }
            return $data;
        }        
        
	}

	Class RecurringPaymentsResponse extends eWayResponse {

		public function parseResponse($response) {
			return parent::parseRecurringResponse($response);
		}

	}
