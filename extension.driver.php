<?php

	require_once(TOOLKIT . '/class.gateway.php');

	class Extension_Google_Blog_Search_Ping extends Extension {

		private static $config_handle = 'google-blog-search-ping-api';

		public function about() {
			return array(
				'name'			=> 'Google Blog Search Pinging Service API',
				'version'		=> '0.9',
				'release-date'	=> '2011-11-07',
				'author'		=> array(
					array(
						'name' => 'Brendan Abbott',
						'email' => 'brendan@bloodbone.ws'
					),
				),
				'description'	=> 'A simple extension that notifies Google Blog Search of updated content using their Pinging API.'
	 		);
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'addCustomPreferenceFieldsets'
				),
				array(
					'page' => '/publish/new/',
					'delegate' => 'EntryPostCreate',
					'callback' => 'entryPostEdit'
				),
				array(
					'page' => '/publish/edit/',
					'delegate' => 'EntryPostEdit',
					'callback' => 'entryPostEdit'
				)
			);
		}

	/*-------------------------------------------------------------------------
		Utilities
	-------------------------------------------------------------------------*/

		public function get($key) {
			return Symphony::Configuration()->get($key, self::$config_handle);
		}

	/*-------------------------------------------------------------------------
		Delegate Callbacks:
	-------------------------------------------------------------------------*/

		public function addCustomPreferenceFieldsets($context) {
			$wrapper = $context['wrapper'];

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Google Blog Search Pinging API'));

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

			// Monitor this section
			$options = array();
			$sectionManager = new SectionManager(Symphony::Engine());
			$sections = $sectionManager->fetch();
			foreach($sections as $section) {
				$options[] = array($section->get('id'), $section->get('id') == $this->get('monitor-sections'), $section->get('name'));
			}

			$label = Widget::Label(__('Monitor this section'));
			$input = Widget::Select('settings[' . self::$config_handle . '][monitor-sections]', $options);

			$label->appendChild($input);
			$group->appendChild($label);

			// Send this URL
			$options = array();
			$pages = Symphony::Database()->fetch('
				SELECT `p`.id
				FROM tbl_pages AS `p`
				RIGHT JOIN tbl_pages_types `pt` ON(`p`.id = `pt`.page_id)
				WHERE `pt`.type = "xml"
			');
			foreach($pages as $page) {
				$page_url = URL . '/' . Administration::instance()->resolvePagePath($page['id']) . '/';
				$options[] = array($page['id'], $page_url == $this->get('ping-url'), $page_url);
			}

			$label = Widget::Label(__('Ping URL'));
			$input = Widget::Select('settings[' . self::$config_handle . '][ping-url]', $options);

			$label->appendChild($input);
			$group->appendChild($label);

			$fieldset->appendChild($group);
			$fieldset->appendChild(
				new XMLElement('p', 'When new entries are created in the selected section, a request will be sent to Google with the Ping URL which should be an RSS, Atom or RDF feed.', array('class' => 'help'))
			);

			$wrapper->appendChild($fieldset);
		}

		public function entryPostEdit(Array &$context) {
			// Check the Entry is being edited in the Monitored section, otherwise return
			if($context['section']->get('id') != $this->get('monitor-sections')) return;

			// Make sure a Ping URL is set.
			if(is_null($this->get('ping-url'))) return;

			// Lets hit Google, with a ping, not a bat. We're matching Google's format
			// specified @ http://www.google.com/help/blogsearch/pinging_API.html#rest.
			// The Site Name and URL will come from the config file, convention over configuration baby.
			$ping_request = array(
				'name' => Symphony::Configuration()->get('sitename', 'general'),
				'url' => URL,
				'changesURL' => $this->get('ping-url')
			);

			// Gateway
			$g = new Gateway;
			$g->init('http://blogsearch.google.com/ping?' . http_build_query($ping_request));

			// Catch the result, we don't really need it though.
			$result = $g->exec();
			Symphony::$Log->pushToLog(__('Google Blog Search API: ') . $result, E_USER_NOTICE, true);
			$info = $g->getInfoLast();

			return $info['http_code'] == 200;
		}
	}
