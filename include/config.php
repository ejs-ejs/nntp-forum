<?php
// see config.example.public.php for the details

return array(
	'nntp' => array(
		'uri' => 'tcp://news.rkm.lt:119',
		'timeout' => 10,
		'options' => array(
			'ssl' => array(
				'verify_peer' => false,
			)
		),

				// posting allowed?
		// if '1', posting will be determined byt the goup info
		'can_post' => 0,
		'can_track' => 0,

		'user' => null,
		'pass' => null
	),

	// Config options for the newsgroup index page
	'newsgroups' => array(
		'filter' => '*',
		'order' => null
	),

	'title' => 'Usenet @ RKM',
	'howto_url' => 'https://rkm.lt/usenet-serveris/',
	'lang_selection' => true,
	'lang_weights' => array(
		'lt' => 1,
		'en' => 0.9,
		),

	'newsfeeds' => array(
		/* a small example newsfeed config
		'example' => array(
			'newsgroups' => 'all.news-*',
			'title' => 'All news',
			'history_duration' => 60 * 60 * 24 * 30, // 1 month
			'limit' => 10
		)
		*/
	),

	'experimental' => array(
		'uudecode' => true // uudecode text/plain message 
	),

	'thumbnails' => array(
		'create' => false,
		'enabled' => true,
		'width' => 400,
		'height' => 400,
		'quality' => 80,
		'expire_time' => 60 * 60 * 24 * 7  // one week
	),

	'cookies' => array(
		'privacy_policy' => 'http://rkm.lt/',
		'gdpr' => '',
		'google_analytics_id' => 'G-CB136B63C7',
	),

	'ldap' => array(
		'host' => null,
		'user' => 'uid=nobody,ou=userlist,dc=example,dc=com',
		'pass' => 'unknown',
		'directory' => 'ou=userlist,dc=example,dc=com'
	),


	'sender_address' => function($login, $name){
		return "Anonymous <anonymous@anonymous.com>";
	},


	'sender_is_self' => function($mail, $login){
		return false;
	},

	// The language file (locale) used for the forum.
	'lang' => autodetect_locale_with_fallback('en'),


	'suggestions' => array(
		'forbidden' => array(),
		'not_found' => array(),
		'unauthorized' => array()
	),

	'cache_dir' => ROOT_DIR . '/cache',
	'cache_lifetime' => 5 * 60,  // 5 minutes

	// Unread tracker settings
	'unread_tracker' => array(
		'file' => null,
		'topic_limit' => 50,
		'unused_expire_time' => 60 * 60 * 24 * 30 * 6
	),

	'subscriptions' => array(
		'watchlist' => null,
	),

	'user_agent' => 'NNTP-Forum/1.1.2b'
);

?>
