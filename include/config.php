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
		'can_post' => 0,
		'user' => null,
		'pass' => null
	),

	// Config options for the newsgroup index page
	'newsgroups' => array(
		'filter' => '*',
		'order' => null
	),

	'title' => 'RKM Newsgroups',
	'howto_url' => 'http://news.rkm.lt',


	'newsfeeds' => array(
	),

	'thumbnails' => array(
		'enabled' => true,
		'width' => 400,
		'height' => 400,
		'quality' => 80,
		'expire_time' => 60 * 60 * 24 * 7  // one week
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

	'user_agent' => 'NNTP-Forum/1.1.2a'
);

?>
