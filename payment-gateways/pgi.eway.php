<?php

	require_once EXTENSIONS . '/pgi_loader/lib/class.paymentgateway.php';
	require_once EXTENSIONS . '/eway/lib/class.api.php';

	Class eWayPaymentGateway extends PaymentGateway {

		public function about() {
			return array(
				'name' => 'eWay Payment Gateway',
				'version' => '0.1',
				'release-date' => '2011-12-14'
			);
		}

		/**
		 * Call the default appendPreferences function that the extension would
		 * use if the Payment Gateway Loader extension wasn't installed. Pass a
		 * dummy context to the appendPreferences, so the function will return
		 * the fieldset even though PGL is installed.
		 *
		 * With the resulting Fieldset, we add the relevant pickable classes
		 */
		public function getPreferencesPane() {
			// Call the extensions appendPreferences function
			$context = array(
				'wrapper' => new XMLElement('dummy'),
				'pgi-loader' => true
			);
			Extension_eWay::appendPreferences($context);

			$fieldset = current($context['wrapper']->getChildren());

			$fieldset->setAttribute('class', 'settings pgi-pickable');
			$fieldset->setAttribute('id', 'eway');

			return $fieldset;
		}

		public static function processTransaction(array $values) {
			return eWayAPI::processPayment($values);
		}

	}