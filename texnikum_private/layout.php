<?php
declare(strict_types=1);

function page_head(string $title, string $description, string $path='index.php', array $params=[], string $image='assets/img/fon1.webp'): void {
    $locale=lang();$canonical=url($path,array_merge($params,['lang'=>$locale]));
    ?><!doctype html>
<html lang="<?= e($locale) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="theme-color" content="#12332f">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php foreach(LANGUAGES as $code=>$name): ?>
<link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(url($path,array_merge($params,['lang'=>$code]))) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e(url($path,array_merge($params,['lang'=>config()['default_language']]))) ?>">
<meta property="og:type" content="<?= isset($params['id'])?'article':'website' ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e(image_url($image)) ?>">
<meta property="og:site_name" content="<?= e(t('school')) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(url('assets/site.css',['v'=>APP_VERSION])) ?>">
<script src="<?= e(url('assets/site.js',['v'=>APP_VERSION])) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main"><?= e(t('skip')) ?></a>
<div class="topbar"><div class="wrap topbar-inner"><span><?= e(t('region')) ?></span><a href="tel:<?= e(config()['phone']) ?>"><?= e(config()['phone_display']) ?></a></div></div>
<header class="site-header">
 <div class="wrap header-inner">
  <a class="brand" href="<?= e(url('index.php',['lang'=>$locale])) ?>"><span class="brand-mark" aria-hidden="true">1<span>T</span></span><span><?= e(t('school')) ?></span></a>
  <button class="menu-toggle" type="button" aria-controls="main-nav" aria-expanded="false"><?= e(t('menu')) ?><span aria-hidden="true">☰</span></button>
  <nav id="main-nav" class="main-nav" aria-label="<?= e(t('menu')) ?>">
   <a href="<?= e(url('index.php',['lang'=>$locale])) ?>"><?= e(t('nav_home')) ?></a>
   <a href="<?= e(url('news.php',['lang'=>$locale])) ?>"><?= e(t('nav_news')) ?></a>
   <a href="<?= e(url('index.php',['lang'=>$locale])) ?>#programs"><?= e(t('nav_programs')) ?></a>
   <a href="<?= e(url('index.php',['lang'=>$locale])) ?>#contact"><?= e(t('nav_contact')) ?></a>
  </nav>
  <nav class="language-switch" aria-label="<?= e(t('language')) ?>">
  <?php foreach(LANGUAGES as $code=>$name): ?><a href="<?= e(url($path,array_merge($params,['lang'=>$code]))) ?>" lang="<?= e($code) ?>" hreflang="<?= e($code) ?>" aria-label="<?= e($name) ?>" <?= $code===$locale?'aria-current="true"':'' ?>><?= ['uz'=>'UZ','kaa'=>'QQ','ru'=>'RU','en'=>'EN'][$code] ?></a><?php endforeach; ?>
  </nav>
 </div>
</header>
<?php }

function page_foot(): void { ?>
<footer class="site-footer"><div class="wrap footer-main"><div><a class="footer-brand" href="<?= e(url('index.php',['lang'=>lang()])) ?>"><?= e(t('school')) ?></a><p><?= e(t('footer_text')) ?></p></div><div class="footer-links"><a href="<?= e(config()['telegram_url']) ?>" target="_blank" rel="noopener noreferrer">Telegram ↗</a><a href="<?= e(config()['instagram_url']) ?>" target="_blank" rel="noopener noreferrer">Instagram ↗</a></div></div><div class="wrap footer-bottom"><p>© <?= date('Y') ?> <?= e(t('school')) ?>. <?= e(t('rights')) ?></p><a href="<?= e(url('admin/index.php')) ?>"><?= e(t('admin')) ?></a></div><div class="wrap footer-credit"><?= e(t('footer_credit')) ?></div></footer>
</body></html>
<?php }

function news_card(array $item): void {
    $target=url('news.php',['id'=>$item['id'],'lang'=>lang()]); ?>
<article class="news-card">
 <a class="news-image-link" href="<?= e($target) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(image_url($item['image'])) ?>" width="800" height="540" loading="lazy" decoding="async" alt=""></a>
 <div class="news-card-content"><time datetime="<?= e(date('c',strtotime($item['published_at']))) ?>"><?= e(date('d.m.Y',strtotime($item['published_at']))) ?></time><h3><a href="<?= e($target) ?>"><?= e($item['title']) ?></a></h3><p><?= e(excerpt($item['body'])) ?></p><a class="text-link" href="<?= e($target) ?>"><?= e(t('read_more')) ?><span aria-hidden="true">↗</span></a></div>
</article>
<?php }
