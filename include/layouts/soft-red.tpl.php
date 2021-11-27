<!DOCTYPE html>
<html lang="<?= $CONFIG['lang'] ?>">
<head>
	<meta charset="utf-8">
	<title><? if ($title) echo(h($title) . ' - '); ?><?= h(lt($CONFIG['title'])) ?></title>
	<link rel="stylesheet" type="text/css" href="/styles/soft-red.css" />


<?	foreach($CONFIG['newsfeeds'] as $name => $newsfeed): ?>
	<link href="/<?= urlencode($name) ?>.xml" rel="alternate" title="<?= h(lt($newsfeed['title'])) ?>" type="application/atom+xml" />
<?	endforeach ?>
</head>
<body class="<?= ha($body_class) ?>">

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
    <? if (isset($CONFIG['cookies']['google_analytics_id'])): ?>
	<script>window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);} gtag('js', new Date());
  gtag('config', '<?= $CONFIG['cookies']['google_analytics_id']?>');
</script>	
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
