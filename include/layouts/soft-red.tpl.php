<!DOCTYPE html>
<html lang="<?= $CONFIG['lang'] ?>">
<head>
	<meta charset="utf-8">
	<title><? if ($title) echo(h($title) . ' - '); ?><?= h(lt($CONFIG['title'])) ?></title>
	<? if ( (isset($CONFIG['cookies']['privacy_policy'])) && (isset($CONFIG['cookies']['google_analytics_id']))): ?>
       <!-- Google Tag Manager -->
  <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=+  'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);+  })(window,document,'script','dataLayer','<?= $CONFIG['cookies']['google_analytics_id']?>');</script>
  <!-- End Google Tag Manager -->
<? endif ?>

	<link rel="stylesheet" type="text/css" href="/styles/soft-red.css" />


<?	foreach($CONFIG['newsfeeds'] as $name => $newsfeed): ?>
	<link href="/<?= urlencode($name) ?>.xml" rel="alternate" title="<?= h(lt($newsfeed['title'])) ?>" type="application/atom+xml" />
<?	endforeach ?>
</head>
<body class="<?= ha($body_class) ?>">
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= $CONFIG['cookies']['google_analytics_id']?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
	<!-- End Google Tag Manager (noscript) -->

<header>
	<h1><a href="/"><?= h(lt($CONFIG['title'])) ?></a></h1>
	<nav>
<?	if ( $CONFIG['lang_selection'] ):
		$languages = all_locales(); ?>
		<ul id="lang">
<?		foreach($languages as $lang => $weight): ?>
			<li><a href="?l=<?= ha($lang); ?>"><?= strtoupper(h($lang)); ?></a></li>
<?		endforeach; ?>
		</ul>
<?	endif ?>


		<ul id="utilities">
<?	foreach($CONFIG['newsfeeds'] as $name => $newsfeed): ?>
			<li><a class="newsfeed" href="/<?= urlencode($name) ?>.xml" type="application/atom+xml" rel="alternate"><?= h(lt($newsfeed['title'])) ?></a></li>
<?	endforeach ?>
<?	if( ! empty($CONFIG['nntp']['user']) and ! empty($CONFIG['subscriptions']['watchlist']) ): ?>
			<li><a class="subscriptions" href="/your/subscriptions"><?= lh('subscriptions', 'link') ?></a></li>
<?	endif ?>
		</ul>
		<ul id="breadcrumbs">
			<li><a href="/"><?= lh('layout', 'breadcrumbs_index') ?></a></li>
<?			foreach($breadcrumbs as $name => $url): ?>
			<li><a href="<?= ha($url); ?>"><?= h($name); ?></a></li>
<?			endforeach; ?>
		</ul>
	</nav>
<? if (isset($CONFIG['howto_url'])): ?>
	<p><a class="help" href="<?= ha($CONFIG['howto_url']) ?>"><?= lh('layout', 'howto_link_text') ?></a><br /></p>
<? endif ?>
</header>

<?= $content ?>

<footer>
<? if (isset($CONFIG['howto_url'])): ?>
	<a class="help" href="<?= ha($CONFIG['howto_url']) ?>"><?= lh('layout', 'howto_link_text') ?></a><br />
<? endif ?>
<? if (isset($CONFIG['cookies']['privacy_policy'])): ?>
	<a class="privacy" href="<?= ha($CONFIG['cookies']['privacy_policy']) ?>"><?= lh('layout', 'privacy_policy_link_text') ?></a><br />
	<? if (isset($CONFIG['cookies']['privacy_policy'])): ?>
       <!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?=$CONFIG['cookies']['google_analytics_id']?>"></script>
<script>window.dataLayer = window.dataLayer || [];  function gtag(){dataLayer.push(arguments);} gtag('js', new Date());  gtag('config', '<?=$CONFIG['cookies']['google_analytics_id']?>');</script>
<? endif ?>

<? endif ?>

<?	list($name, $version) = explode('/', $CONFIG['user_agent'], 2) ?>
	<?= l('layout', 'credits', $name, $version, '<a href="http://arkanis.de/">Stephan Soller</a>') ?>
	<?= l('layout', 'credits_3rd_party', '<a href="http://www.famfamfam.com/lab/icons/silk/">Silk Icons</a>', '<a href="http://www.famfamfam.com/">famfamfam.com</a>') ?>
</footer>

<? foreach($scripts as $script): ?>
<script src="/scripts/<?= ha($script) ?>"></script>
<? endforeach ?>

</body>
</html>
