<?php

	require_once EXTENSIONS . '/eway/lib/class.api.php';

	class Extension_eWay extends Extension {

		public function about() {
			return array(
				'name' => 'eWay',
				'version' => '0.1',
				'release-date' => '2011-12-14',
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

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function install() {
			Symphony::Configuration()->set('production-customer-id', '', 'eway');
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
		public static function appendPreferences($context) {
			// If the Payment Gateway Interface extension is installed, don't
			// double display the preference, unless this function is called from
			// the `pgi-loader` context.
			if(
				Symphony::ExtensionManager()->fetchStatus('pgi_loader') == EXTENSION_ENABLED
				xor isset($context['pgi-loader'])
			) return;

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
				array('development', eWayAPI::isTesting(), __('Development')),
				array('production', !eWayAPI::isTesting(), __('Production'))
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

	}
