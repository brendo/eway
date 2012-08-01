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

	Abstract Class PGI_Response {
		const GATEWAY_ERROR = 100;
		const DATA_ERROR = 200;
	}