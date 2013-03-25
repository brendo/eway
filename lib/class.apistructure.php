<?php

	Abstract Class PGI_MethodConfiguration {
		abstract static function getGatewayURI();
		abstract static function getDefaults();
		abstract static function getRequiredFields();
		abstract static function getApprovedCodes();
	}

	Abstract Class PGI_Request {
		public static function start($uri, $xml, $timeout = 60) {
			require_once(TOOLKIT . '/class.gateway.php');
            
			$curl = new Gateway;
			$curl->init($uri);            
			$curl->setopt('POST', true);
			$curl->setopt('POSTFIELDS', $xml);
			$curl->setopt('TIMEOUT', $timeout);
			return $curl;
		}
	}
    
    
	Abstract Class Recurring_Request {
		public static function start($uri, $xml, $timeout = 60) {
            
            // Make sure there isn't a xml tag in the body.
            $xml_body = str_replace('<?xml version="1.0"?>', '', $xml);
            
            $data = '<?xml version="1.0" encoding="utf-8"?>
                    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                      <soap:Header>
                            <eWAYHeader xmlns="http://www.eway.com.au/gateway/rebill/manageRebill">
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

	Abstract Class PGI_Response {
		const GATEWAY_ERROR = 100;
		const DATA_ERROR = 200;
	}