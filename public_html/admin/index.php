<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_init.php';
headers_common(true);require_installed();session_start_safe();
$view=is_string($_GET['view']??null)?$_GET['view']:'list';
if(!in_array($view,['list','login','edit','delete','password','help'],true))$view='list';
$error='';$errors=[];$editor=null;$user=current_admin();
if(!$user)$view='login';
if($user && $view==='login')redirect_admin();

if($_SERVER['REQUEST_METHOD']==='POST') {
    if((int)($_SERVER['CONTENT_LENGTH']??0)>ini_bytes((string)ini_get('post_max_size'))){http_response_code(413);exit('Yuklanayotgan fayl hosting ruxsat etgan hajmdan katta. Kichikroq rasm bilan qayta urinib ko‘ring.');}
    verify_csrf();
    $action=is_string($_POST['action']??null)?$_POST['action']:'';
    if($action==='login') {
        if(!login_allowed()){http_response_code(429);$error='Kirish urinishlari ko‘payib ketdi. 15 daqiqadan keyin qayta urinib ko‘ring.';}
        else {
            $username=is_string($_POST['username']??null)?trim($_POST['username']):'';
            $password=is_string($_POST['password']??null)?$_POST['password']:'';
            $s=db()->prepare('SELECT * FROM tx_admins WHERE username=?');$s->execute([mb_substr($username,0,50)]);$found=$s->fetch();
            $hash=$found['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
            $verified=password_verify($password,$hash);
            if($verified && $found) {
                session_regenerate_id(true);$_SESSION=[];
                $_SESSION['admin_id']=(int)$found['id'];$_SESSION['auth_version']=(int)$found['session_version'];
                $_SESSION['signed_in']=$_SESSION['last_seen']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
                clear_login_attempts();
                if(password_needs_rehash($found['password_hash'],PASSWORD_DEFAULT)){
                    $s=db()->prepare('UPDATE tx_admins SET password_hash=? WHERE id=?');$s->execute([password_hash($password,PASSWORD_DEFAULT),$found['id']]);
                }
                redirect_admin();
            }
            http_response_code(401);$error='Login yoki parol noto‘g‘ri.';
        }
        $view='login';
    } else {
        $user=require_admin();
        if($action==='logout') {
            $_SESSION=[];$params=session_get_cookie_params();
            setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>$params['path'],'secure'=>$params['secure'],'httponly'=>true,'samesite'=>'Strict']);
            session_destroy();redirect_admin('view=login');
        }
        if($action==='password') {
            $current=is_string($_POST['current_password']??null)?$_POST['current_password']:'';
            $password=is_string($_POST['new_password']??null)?$_POST['new_password']:'';
            if(!password_verify($current,$user['password_hash']))$error='Joriy parol noto‘g‘ri.';
            elseif($problem=password_error($password))$error=$problem;
            elseif($password!==($_POST['repeat_password']??''))$error='Yangi parollar bir xil emas.';
            else {
                $s=db()->prepare('UPDATE tx_admins SET password_hash=?,session_version=session_version+1 WHERE id=?');$s->execute([password_hash($password,PASSWORD_DEFAULT),$user['id']]);
                $_SESSION=[];session_regenerate_id(true);$_SESSION['flash']='Parol yangilandi. Yangi parol bilan kiring.';redirect_admin('view=login');
            }
            $view='password';
        }
        if($action==='save') {
            $view='edit';$id=positive_id($_POST['id']??0);$existing=$id?find_news($id):null;
            if($id && !$existing){http_response_code(404);exit('Yangilik topilmadi.');}
            $status=($_POST['status']??'draft')==='published'?'published':'draft';
            $date=is_string($_POST['published_at']??null)?$_POST['published_at']:'';
            $parsed=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date);
            if(!$parsed || $parsed->format('Y-m-d\TH:i')!==$date)$errors[]='Sana va vaqtni to‘g‘ri kiriting.';
            elseif($status==='published' && $parsed->getTimestamp()>time()+60)$errors[]='E’lon sanasi kelajakda bo‘lishi mumkin emas. Sana va vaqtni tekshiring yoki qoralama sifatida saqlang.';
            $values=[];
            foreach(LANGUAGES as $code=>$name) {
                $row=$_POST['translations'][$code]??[];if(!is_array($row))$row=[];
                $title=is_string($row['title']??null)?trim($row['title']):'';$body=is_string($row['body']??null)?trim($row['body']):'';
                $values[$code]=['title'=>$title,'body'=>$body];
                if(($code==='uz'||$status==='published') && (!$title||!$body))$errors[]=$name.': sarlavha va matnni to‘ldiring.';
                if(mb_strlen($title)>180)$errors[]=$name.': sarlavha 180 belgidan oshmasin.';
                if(mb_strlen($body)>50000)$errors[]=$name.': matn 50 000 belgidan oshmasin.';
            }
            $editor=['id'=>$id,'version'=>(int)($_POST['version']??0),'status'=>$status,'published_at'=>$parsed?$parsed->format('Y-m-d H:i:s'):now(),'image'=>$existing['image']??'','translations'=>$values];
            $newImage='';$oldImage=$existing['image']??'';$image=$oldImage;
            if(!$errors) {
                try {
                    if(isset($_POST['remove_image']))$image='';
                    if(isset($_FILES['image']) && ($_FILES['image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$image=$newImage=uploaded_image($_FILES['image']);
                    $pdo=db();$pdo->beginTransaction();
                    if($id){
                        $s=$pdo->prepare('SELECT version FROM tx_news WHERE id=? FOR UPDATE');$s->execute([$id]);$version=$s->fetchColumn();
                        if(!$version || (int)$version!==(int)($_POST['version']??0))throw new DomainException('Bu yangilik boshqa oynada o‘zgartirilgan. Matningizni nusxalab oling va sahifani qayta oching.');
                        $s=$pdo->prepare('UPDATE tx_news SET image=?,status=?,published_at=?,updated_at=?,version=version+1 WHERE id=?');$s->execute([$image,$status,$editor['published_at'],now(),$id]);
                    }else{
                        $s=$pdo->prepare('INSERT INTO tx_news (image,status,published_at,created_at,updated_at) VALUES (?,?,?,?,?)');$s->execute([$image,$status,$editor['published_at'],now(),now()]);$id=(int)$pdo->lastInsertId();
                    }
                    $s=$pdo->prepare('INSERT INTO tx_news_translations (news_id,lang,title,body) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),body=VALUES(body)');
                    foreach($values as $code=>$value)$s->execute([$id,$code,$value['title'],$value['body']]);
                    $pdo->commit();
                    if($oldImage && $oldImage!==$image)delete_uploaded_image($oldImage);
                    $_SESSION['flash']=$status==='published'?'Yangilik saqlandi va saytda e’lon qilindi.':'Qoralama saqlandi. U tashrifchilarga ko‘rinmaydi.';
                    redirect_admin();
                }catch(InvalidArgumentException|DomainException $ex){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();if($newImage)delete_uploaded_image($newImage);$errors[]=$ex->getMessage();http_response_code($ex instanceof DomainException?409:422);}
                catch(Throwable $ex){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();if($newImage)delete_uploaded_image($newImage);error_log('News save: '.$ex->getMessage());$errors[]='Saqlashda xato yuz berdi. Matningizni nusxalab oling va sayt mas’uliga murojaat qiling.';http_response_code(500);}
            }else http_response_code(422);
        }
        if($action==='delete') {
            $id=positive_id($_POST['id']??0);$pdo=db();$pdo->beginTransaction();
            try{
                $s=$pdo->prepare('SELECT image,version FROM tx_news WHERE id=? FOR UPDATE');$s->execute([$id]);$old=$s->fetch();
                if(!$old || (int)$old['version']!==(int)($_POST['version']??0))throw new DomainException('Yangilik o‘zgartirilgan yoki o‘chirilgan. Ro‘yxatni yangilang.');
                $s=$pdo->prepare('DELETE FROM tx_news WHERE id=?');$s->execute([$id]);$pdo->commit();
                delete_uploaded_image($old['image']);$_SESSION['flash']='Yangilik barcha tillarda o‘chirildi.';redirect_admin();
            }catch(DomainException $ex){$pdo->rollBack();http_response_code(409);$error=$ex->getMessage();$view='list';}
        }
    }
}

function admin_head(string $title): void { ?><!doctype html><html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> — Texnikum boshqaruvi</title><link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>"><link rel="stylesheet" href="<?= e(url('assets/admin.css',['v'=>APP_VERSION])) ?>"><script src="<?= e(url('assets/admin.js',['v'=>APP_VERSION])) ?>" defer></script></head><body class="admin-body"><?php }
function flash_messages(string $error,array $errors=[]): void {
    if(!empty($_SESSION['flash'])){echo '<div class="alert" role="status">'.e($_SESSION['flash']).'</div>';unset($_SESSION['flash']);}
    if($error)echo '<div class="alert error" role="alert">'.e($error).'</div>';
    if($errors){echo '<div class="alert error" role="alert"><ul>';foreach($errors as $item)echo '<li>'.e($item).'</li>';echo '</ul></div>';}
}

if($view==='login') {
    admin_head('Administrator kirishi'); ?>
<main class="login-shell"><a class="login-brand" href="<?= e(url('index.php')) ?>"><span class="brand-mark" aria-hidden="true">1<span>T</span></span><h1>Xo‘jayli tumani<br>1-son texnikumi</h1></a><section class="panel"><h2>Administrator kirishi</h2><p class="editor-head">Yangiliklarni boshqarish uchun hisobingizga kiring.</p><?php flash_messages($error); ?><form method="post" action="<?= e(url('admin/index.php',['view'=>'login'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="login"><label class="field"><span>Login</span><input name="username" autocomplete="username" maxlength="50" required value="<?= e(is_string($_POST['username']??null)?mb_substr($_POST['username'],0,50):'') ?>"></label><label class="field"><span>Parol</span><input type="password" name="password" autocomplete="current-password" required maxlength="200"></label><button class="button button-primary" type="submit">Kirish →</button></form></section><p class="login-footer"><a href="<?= e(url('index.php')) ?>">← Saytga qaytish</a></p></main></body></html>
<?php exit; }

$user=require_admin();
admin_head(match($view){'edit'=>'Yangilik tahrirlash','delete'=>'Yangilikni o‘chirish','password'=>'Parolni o‘zgartirish','help'=>'Yordam',default=>'Yangiliklar'});
?>
<div class="admin-layout"><aside class="admin-sidebar"><a class="admin-logo" href="<?= e(url('admin/index.php')) ?>">Texnikum 1<small>BOSHQARUV PANELI</small></a><nav aria-label="Boshqaruv menyusi"><a href="<?= e(url('admin/index.php')) ?>" <?= in_array($view,['list','edit','delete'],true)?'aria-current="page"':'' ?>>Yangiliklar</a><a href="<?= e(url('admin/index.php',['view'=>'password'])) ?>" <?= $view==='password'?'aria-current="page"':'' ?>>Parolni o‘zgartirish</a><a href="<?= e(url('admin/index.php',['view'=>'help'])) ?>" <?= $view==='help'?'aria-current="page"':'' ?>>Yordam</a><a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">Saytni ko‘rish ↗</a></nav><form class="logout" method="post" action="<?= e(url('admin/index.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="button" type="submit">Chiqish</button></form></aside><main class="admin-main">
<?php flash_messages($error,$errors);
if($view==='list'){
    $q=is_string($_GET['q']??null)?mb_substr(trim($_GET['q']),0,100):'';$page=max(1,positive_id($_GET['page']??1));$perPage=12;
    $where=$q!==''?' WHERE EXISTS (SELECT 1 FROM tx_news_translations f WHERE f.news_id=n.id AND f.title LIKE ?)':'';$args=$q!==''?['%'.$q.'%']:[];
    $s=db()->prepare('SELECT COUNT(*) FROM tx_news n'.$where);$s->execute($args);$total=(int)$s->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);
    $s=db()->prepare("SELECT n.*,t.title FROM tx_news n LEFT JOIN tx_news_translations t ON t.news_id=n.id AND t.lang='uz'".$where.' ORDER BY n.published_at DESC,n.id DESC LIMIT ? OFFSET ?');
    $i=1;foreach($args as $a)$s->bindValue($i++,$a);$s->bindValue($i++,$perPage,PDO::PARAM_INT);$s->bindValue($i,($page-1)*$perPage,PDO::PARAM_INT);$s->execute();$items=$s->fetchAll();
    ?><div class="admin-title"><div><p class="eyebrow">KONTENT BOSHQARUVI</p><h1>Yangiliklar</h1><p><?= e($user['username']) ?> · Jami <?= $total ?> ta yangilik</p></div><a class="button button-primary" href="<?= e(url('admin/index.php',['view'=>'edit'])) ?>">+ Yangilik qo‘shish</a></div><div class="admin-toolbar"><form method="get"><label class="label" for="search">Qidirish</label><input id="search" name="q" value="<?= e($q) ?>" placeholder="Yangilik sarlavhasi" maxlength="100"><button class="button button-outline" type="submit">Qidirish</button><?php if($q): ?><a href="<?= e(url('admin/index.php')) ?>">Tozalash</a><?php endif; ?></form></div><div class="admin-list"><?php if(!$items): ?><div class="panel empty-panel"><p>Yangilik topilmadi.</p><a href="<?= e(url('admin/index.php',['view'=>'edit'])) ?>" class="button button-primary">Yangilik qo‘shish</a></div><?php endif;foreach($items as $item): ?><article class="admin-item"><img src="<?= e(image_url($item['image'])) ?>" width="110" height="82" alt="" loading="lazy"><div><span class="badge <?= $item['status']==='draft'?'draft':'' ?>"><?= $item['status']==='published'?'E’lon qilingan':'Qoralama' ?></span><h2><?= e($item['title']?:'Sarlavhasiz qoralama') ?></h2><p><?= e(date('d.m.Y H:i',strtotime($item['published_at']))) ?> · ID <?= (int)$item['id'] ?></p></div><div class="item-actions"><?php if($item['status']==='published'): ?><a href="<?= e(url('news.php',['id'=>$item['id'],'lang'=>'uz'])) ?>" target="_blank" rel="noopener">Ko‘rish ↗</a><?php endif; ?><a href="<?= e(url('admin/index.php',['view'=>'edit','id'=>$item['id']])) ?>">Tahrirlash</a><a class="danger" href="<?= e(url('admin/index.php',['view'=>'delete','id'=>$item['id']])) ?>">O‘chirish</a></div></article><?php endforeach; ?></div><?php if($pages>1): ?><nav class="pagination" aria-label="Sahifalar"><?php if($page>1): ?><a href="<?= e(url('admin/index.php',['q'=>$q,'page'=>$page-1])) ?>">← Oldingi</a><?php endif; ?><span><?= $page ?> / <?= $pages ?></span><?php if($page<$pages): ?><a href="<?= e(url('admin/index.php',['q'=>$q,'page'=>$page+1])) ?>">Keyingi →</a><?php endif; ?></nav><?php endif;
}
if($view==='edit') {
    if(!$editor){$id=positive_id($_GET['id']??0);$editor=$id?find_news($id):['id'=>0,'version'=>0,'image'=>'','status'=>'draft','published_at'=>now(),'translations'=>[]];}
    if(!$editor){http_response_code(404);echo '<div class="alert error">Yangilik topilmadi.</div>';}
    else {
    ?><div class="admin-title"><div><p class="eyebrow">YANGILIKLAR</p><h1><?= $editor['id']?'Yangilikni tahrirlash':'Yangi yangilik' ?></h1></div><a class="text-link" href="<?= e(url('admin/index.php')) ?>">← Ro‘yxatga qaytish</a></div><p class="editor-head">E’lon qilish uchun to‘rtta tildagi sarlavha va matnni to‘ldiring. Hali tayyor bo‘lmagan yangilikni qoralama sifatida saqlang. Matnga HTML kodi kiritish shart emas.</p><form id="news-editor" method="post" enctype="multipart/form-data" action="<?= e(url('admin/index.php',['view'=>'edit','id'=>$editor['id']])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$editor['id'] ?>"><input type="hidden" name="version" value="<?= (int)$editor['version'] ?>"><div class="panel"><div class="form-grid"><label class="field"><span>Holati</span><select name="status"><option value="draft" <?= $editor['status']==='draft'?'selected':'' ?>>Qoralama — faqat admin ko‘radi</option><option value="published" <?= $editor['status']==='published'?'selected':'' ?>>E’lon qilingan — hammaga ko‘rinadi</option></select></label><label class="field"><span>Sana va vaqt — Toshkent vaqti</span><input type="datetime-local" name="published_at" required value="<?= e(date('Y-m-d\TH:i',strtotime($editor['published_at']))) ?>"></label></div></div>
    <?php foreach(LANGUAGES as $code=>$name):$value=$editor['translations'][$code]??['title'=>'','body'=>'']; ?><details class="language-editor" <?= $code==='uz'||$errors?'open':'' ?>><summary><?= e($name) ?></summary><div><label class="field"><span>Sarlavha</span><input name="translations[<?= e($code) ?>][title]" lang="<?= e($code) ?>" maxlength="180" value="<?= e($value['title']) ?>" <?= $code==='uz'?'required':'' ?>></label><label class="field"><span>Yangilik matni</span><textarea name="translations[<?= e($code) ?>][body]" lang="<?= e($code) ?>" maxlength="50000" rows="9" <?= $code==='uz'?'required':'' ?>><?= e($value['body']) ?></textarea></label><p class="hint">Yangi xatboshi uchun ikki marta Enter bosing.</p></div></details><?php endforeach; ?>
    <div class="panel"><h2>Yangilik rasmi</h2><?php if($editor['image']): ?><p class="hint">Hozirgi rasm</p><img class="current-image" src="<?= e(image_url($editor['image'])) ?>" alt="Hozirgi yangilik rasmi"><label class="check-inline"><input type="checkbox" name="remove_image" value="1"> Hozirgi rasmni olib tashlash</label><?php endif; ?><label class="field"><span>Yangi rasm tanlash</span><input id="news-image" type="file" name="image" accept="image/jpeg,image/png,image/webp"><span class="hint">JPG, PNG yoki WebP. Ko‘pi bilan 5 MB va 12 megapiksel. Rasm saqlashda avtomatik kichraytiriladi.</span></label><img id="image-preview" class="preview-image" alt="Tanlangan rasm" hidden><p class="hint">Rasm tanlamasangiz, mavjud rasm saqlanadi. Yangi rasm tanlansa, avvalgi rasm almashtiriladi.</p></div><div class="form-actions"><button class="button button-primary" type="submit">Saqlash</button><a class="button button-outline" href="<?= e(url('admin/index.php')) ?>">Bekor qilish</a></div></form><?php }
}
if($view==='delete') {
    $item=find_news(positive_id($_GET['id']??0));
    if(!$item){http_response_code(404);echo '<div class="alert error">Yangilik topilmadi.</div>';}
    else { ?><div class="admin-title"><h1>Yangilikni o‘chirish</h1></div><section class="panel"><p>Quyidagi yangilik barcha to‘rtta tilda o‘chiriladi. Bu amalni panel orqali qaytarib bo‘lmaydi. Uni vaqtincha yashirish uchun tahrirlash oynasida “Qoralama” holatini tanlang.</p><p class="delete-summary"><?= e($item['translations']['uz']['title']??'Yangilik') ?></p><form method="post" action="<?= e(url('admin/index.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="version" value="<?= (int)$item['version'] ?>"><div class="form-actions"><button class="button button-danger" type="submit">Ha, yangilikni o‘chirish</button><a class="button button-outline" href="<?= e(url('admin/index.php')) ?>">Bekor qilish</a></div></form></section><?php }
}
if($view==='password') { ?><div class="admin-title"><h1>Parolni o‘zgartirish</h1></div><div class="panel"><p class="editor-head">Yangi parol kamida 12 belgidan iborat bo‘lsin. Parol o‘zgartirilgach, barcha ochiq hisob sessiyalari bekor qilinadi.</p><form method="post" action="<?= e(url('admin/index.php',['view'=>'password'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="password"><label class="field"><span>Joriy parol</span><input type="password" name="current_password" autocomplete="current-password" required></label><div class="form-grid"><label class="field"><span>Yangi parol</span><input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label class="field"><span>Yangi parolni takrorlang</span><input type="password" name="repeat_password" autocomplete="new-password" minlength="12" maxlength="72" required></label></div><div class="form-actions"><button class="button button-primary" type="submit">Parolni yangilash</button></div></form></div><?php }
if($view==='help') { ?><div class="admin-title"><h1>Qisqa yo‘riqnoma</h1></div><article class="panel admin-help"><h2>Yangilik qo‘shish</h2><ol><li>“Yangiliklar” sahifasida “+ Yangilik qo‘shish” tugmasini bosing.</li><li>To‘rtta til bo‘limini navbat bilan ochib, sarlavha va matnni kiriting.</li><li>Sana va vaqtni tekshiring. Kerak bo‘lsa, rasm yuklang.</li><li>Holatini “E’lon qilingan” qilib, “Saqlash” tugmasini bosing.</li><li>Ro‘yxatdagi “Ko‘rish” havolasi bilan natijani tekshiring. Saytda to‘rtta tilni ham ochib ko‘ring.</li></ol><h2>Tahrirlash yoki yashirish</h2><p>Yangilik yonidagi “Tahrirlash” tugmasini bosing. Kerakli matn yoki rasmni o‘zgartiring va saqlang. Yangilikni vaqtincha yashirish uchun holatini “Qoralama” qiling.</p><h2>O‘chirish</h2><p>“O‘chirish” tugmasini bosing, sarlavhani tekshiring va keyingi sahifada amalni tasdiqlang. O‘chirish barcha tillarga taalluqli. Tasdiqlanmaguncha yangilik o‘chmaydi.</p><h2>Tarjimalar</h2><p>Panel matnlarni avtomatik tarjima qilmaydi. Tayyor tarjimalarni tegishli maydonlarga kiriting. E’lon qilish uchun to‘rtta til to‘ldirilishi kerak; qoralama uchun o‘zbekcha sarlavha va matn yetarli.</p><h2>Hisob</h2><p>Ish tugagach “Chiqish” tugmasini bosing. 30 daqiqa harakatsizlikdan so‘ng qayta kirish talab qilinadi. Parol unutilsa, saytni o‘rnatgan mas’ulga murojaat qiling.</p></article><?php }
?></main></div></body></html>
