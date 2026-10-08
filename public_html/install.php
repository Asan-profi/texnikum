<?php
declare(strict_types=1);
require __DIR__.'/_init.php';
headers_common(true);session_start_safe();
$errors=[];$done=false;$config=config();
$checks=['PHP 8.2 yoki yuqori'=>version_compare(PHP_VERSION,'8.2','>='),'PDO MySQL'=>extension_loaded('pdo_mysql'),'mbstring'=>extension_loaded('mbstring'),'GD rasm kutubxonasi'=>extension_loaded('gd'),'JPEG va PNG'=>function_exists('imagecreatefromjpeg')&&function_exists('imagecreatefrompng'),'WebP o‘qish'=>function_exists('imagecreatefromwebp'),'Fayl turini aniqlash'=>extension_loaded('fileinfo'),'Rasm papkasiga yozish'=>is_writable(__DIR__.'/uploads'),'Xizmat papkasiga yozish'=>is_writable(dirname(__DIR__).'/texnikum_private/runtime'),'O‘rnatish kaliti'=>is_string($config['setup_key']??null)&&strlen($config['setup_key'])>=32];
$checks['Rasm yuklash chegarasi kamida 5 MB']=ini_bytes((string)ini_get('upload_max_filesize'))>=MAX_IMAGE_BYTES;
$checks['So‘rov chegarasi kamida 8 MB']=ini_bytes((string)ini_get('post_max_size'))>=8388608;
$checks['PHP xotirasi kamida 128 MB']=ini_bytes((string)ini_get('memory_limit'))>=134217728;
if(is_file(dirname(__DIR__).'/texnikum_private/runtime/installed.flag')) {http_response_code(403);exit('Sayt avval o‘rnatilgan. install.php faylini hostingdan o‘chiring.');}
try{$pdo=db();$checks['Bazaga ulanish']=true;if(installed()){http_response_code(403);exit('Sayt avval o‘rnatilgan. install.php faylini hostingdan o‘chiring.');}}
catch(Throwable $ex){$checks['Bazaga ulanish']=false;error_log('Install connection: '.$ex->getMessage());}
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(in_array(false,$checks,true))$errors[]='Avval quyidagi tekshiruvlardagi kamchiliklarni tuzating.';
    $token=is_string($_POST['setup_key']??null)?$_POST['setup_key']:'';
    if(strlen((string)($config['setup_key']??''))<32 || !hash_equals((string)$config['setup_key'],$token))$errors[]='O‘rnatish kaliti noto‘g‘ri.';
    $username=is_string($_POST['username']??null)?trim($_POST['username']):'';$password=is_string($_POST['password']??null)?$_POST['password']:'';
    if(!preg_match('/^[A-Za-z0-9_.-]{3,50}$/D',$username))$errors[]='Login 3–50 ta lotin harfi, raqam, nuqta, tire yoki pastki chiziqdan iborat bo‘lsin.';
    if($problem=password_error($password))$errors[]=$problem;
    if($password!==($_POST['password_repeat']??''))$errors[]='Parollar bir xil emas.';
    if(!$errors){
        $lock=fopen(dirname(__DIR__).'/texnikum_private/runtime/install.lock','c');
        try{
            if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('Installation lock unavailable.');
            if(installed())throw new RuntimeException('Already installed.');
            foreach(explode(';',file_get_contents(dirname(__DIR__).'/texnikum_private/schema.sql')) as $sql)if(trim($sql))$pdo->exec($sql);
            if((int)$pdo->query('SELECT COUNT(*) FROM tx_news')->fetchColumn()>0)throw new RuntimeException('Database already contains news. Use an empty database.');
            $pdo->beginTransaction();
            $s=$pdo->prepare('INSERT INTO tx_admins (username,password_hash,created_at) VALUES (?,?,?)');$s->execute([$username,password_hash($password,PASSWORD_DEFAULT),now()]);
            $seed=json_decode(file_get_contents(dirname(__DIR__).'/texnikum_private/seed.json'),true,512,JSON_THROW_ON_ERROR);
            foreach($seed as $item){
                $s=$pdo->prepare("INSERT INTO tx_news (image,status,published_at,created_at,updated_at) VALUES (?,'published',?,?,?)");$s->execute([$item['image'],$item['published_at'],now(),now()]);$id=(int)$pdo->lastInsertId();
                $s=$pdo->prepare('INSERT INTO tx_news_translations (news_id,lang,title,body) VALUES (?,?,?,?)');foreach($item['translations'] as $language=>$value)$s->execute([$id,$language,$value['title'],$value['body']]);
            }
            $pdo->commit();file_put_contents(dirname(__DIR__).'/texnikum_private/runtime/installed.flag',now(),LOCK_EX);
            session_regenerate_id(true);$_SESSION=[];$done=true;
        }catch(Throwable $ex){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Installation: '.$ex->getMessage());$errors[]='O‘rnatish tugamadi. Baza ruxsatlari va hosting yo‘riqnomasini tekshiring. Avvalgi ma’lumotlarni o‘chirmang.';}
        finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
    }
    if($errors)http_response_code(422);
}
?><!doctype html><html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Texnikum saytini o‘rnatish</title><link rel="stylesheet" href="<?= e(url('assets/admin.css',['v'=>APP_VERSION])) ?>"></head><body class="admin-body"><main class="install-shell"><h1>Saytni o‘rnatish</h1><p class="editor-head">Bu sahifa faqat saytni birinchi marta ishga tushirish uchun.</p><section class="panel"><?php if($done): ?><h2>O‘rnatish yakunlandi</h2><p>Administrator hisobi yaratildi va 6 ta yangilik to‘rtta tilda yuklandi.</p><ol><li>Hostingdan <strong>install.php</strong> faylini o‘chiring.</li><li>Xususiy config.php faylida <strong>setup_key</strong> qiymatini bo‘sh qoldiring.</li><li>Tanlagan login va parolingiz bilan admin panelga kiring.</li></ol><a class="button button-primary" href="<?= e(url('admin/index.php')) ?>">Admin panelga kirish</a><?php else: ?><h2>Hostingni tekshirish</h2><ul class="checklist"><?php foreach($checks as $name=>$ok): ?><li><span><?= e($name) ?></span><span class="<?= $ok?'check-ok':'check-bad' ?>"><?= $ok?'Tayyor':'Tekshiring' ?></span></li><?php endforeach; ?></ul><?php if($errors): ?><div class="alert error" role="alert"><ul><?php foreach($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?><form method="post"><?= csrf_field() ?><label class="field"><span>O‘rnatish kaliti</span><input type="password" name="setup_key" required autocomplete="off"><span class="hint">Xususiy config.php fayliga yozgan setup_key qiymati.</span></label><label class="field"><span>Administrator logini</span><input name="username" required pattern="[A-Za-z0-9_.\-]{3,50}" minlength="3" maxlength="50" autocomplete="username" value="<?= e(is_string($_POST['username']??null)?$_POST['username']:'') ?>"></label><div class="form-grid"><label class="field"><span>Administrator paroli</span><input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><label class="field"><span>Parolni takrorlang</span><input type="password" name="password_repeat" required minlength="12" maxlength="72" autocomplete="new-password"></label></div><p class="hint">Parol kamida 12 belgidan iborat bo‘lsin. Uni xavfsiz joyda saqlang.</p><div class="form-actions"><button class="button button-primary" type="submit" <?= in_array(false,$checks,true)?'disabled':'' ?>>Saytni o‘rnatish</button></div></form><?php endif; ?></section></main></body></html>
