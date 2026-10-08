<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
headers_common();require_installed();
if(isset($_GET['id'])) {
    $id=positive_id($_GET['id']);$item=$id?find_news($id):null;
    if(!$item || $item['status']!=='published' || $item['published_at']>now() || empty($item['translations'][lang()]['title'])) {
        http_response_code(404);header('X-Robots-Tag: noindex');page_head(t('not_found').' — '.t('school'),t('not_found_text'),'news.php');
        ?><main id="main" class="wrap section error-page"><p class="eyebrow">404</p><h1><?= e(t('not_found')) ?></h1><p><?= e(t('not_found_text')) ?></p><a class="button button-primary" href="<?= e(url('index.php',['lang'=>lang()])) ?>"><?= e(t('home_return')) ?></a></main><?php page_foot();exit;
    }
    $translation=$item['translations'][lang()];
    page_head($translation['title'].' — '.t('school'),excerpt($translation['body'],155),'news.php',['id'=>$id],$item['image']);
    ?><main id="main" class="wrap article-page"><a class="text-link back-link" href="<?= e(url('news.php',['lang'=>lang()])) ?>">← <?= e(t('back_news')) ?></a><article><div class="article-heading"><p class="eyebrow"><?= e(t('nav_news')) ?></p><h1><?= e($translation['title']) ?></h1><p class="article-meta"><?= e(t('published')) ?> <time datetime="<?= e(date('c',strtotime($item['published_at']))) ?>"><?= e(date('d.m.Y · H:i',strtotime($item['published_at']))) ?></time></p></div><?php if($item['image']): ?><img class="article-image" src="<?= e(image_url($item['image'])) ?>" alt="<?= e($translation['title']) ?>" width="1200" height="800" fetchpriority="high"><?php endif; ?><div class="article-body"><?= paragraphs($translation['body']) ?></div></article></main><?php page_foot();exit;
}
$page=max(1,positive_id($_GET['page']??1));$perPage=9;$total=public_news_count();$pages=max(1,(int)ceil($total/$perPage));
if($page>$pages){http_response_code(404);$page=$pages;}
$news=public_news($perPage,($page-1)*$perPage);
page_head(t('nav_news').' — '.t('school'),t('news_text'),'news.php',$page>1?['page'=>$page]:[]);
?><main id="main" class="wrap section"><div class="section-heading"><div><p class="eyebrow"><?= e(t('news_eyebrow')) ?></p><h1><?= e(t('news_title')) ?></h1><p><?= e(t('news_text')) ?></p></div></div><div class="news-grid"><?php if(!$news): ?><p class="empty-state"><?= e(t('empty_news')) ?></p><?php endif;foreach($news as $item)news_card($item); ?></div><?php if($pages>1): ?><nav class="pagination" aria-label="<?= e(t('page')) ?>"><?php if($page>1): ?><a class="button button-outline" href="<?= e(url('news.php',['lang'=>lang(),'page'=>$page-1])) ?>">← <?= e(t('previous')) ?></a><?php endif; ?><span><?= $page ?> / <?= $pages ?></span><?php if($page<$pages): ?><a class="button button-outline" href="<?= e(url('news.php',['lang'=>lang(),'page'=>$page+1])) ?>"><?= e(t('next')) ?> →</a><?php endif; ?></nav><?php endif; ?></main><?php page_foot(); ?>
