<?php
declare(strict_types=1);
require __DIR__.'/_init.php';
require_installed();
header('Content-Type: application/xml; charset=UTF-8');header('Cache-Control: public, max-age=300');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach(LANGUAGES as $language=>$label){
    echo '<url><loc>'.e(url('index.php',['lang'=>$language])).'</loc></url>';
    echo '<url><loc>'.e(url('news.php',['lang'=>$language])).'</loc></url>';
}
$s=db()->prepare("SELECT id,updated_at FROM tx_news WHERE status='published' AND published_at<=? ORDER BY id");$s->execute([now()]);
foreach($s as $row)foreach(LANGUAGES as $language=>$label)echo '<url><loc>'.e(url('news.php',['id'=>$row['id'],'lang'=>$language])).'</loc><lastmod>'.e(date('c',strtotime($row['updated_at']))).'</lastmod></url>';
echo '</urlset>';
