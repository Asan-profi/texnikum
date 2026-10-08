<?php
declare(strict_types=1);

const APP_VERSION = '1.0.0';
const LANGUAGES = ['uz' => 'O‘zbekcha', 'kaa' => 'Qaraqalpaqsha', 'ru' => 'Русский', 'en' => 'English'];
const MAX_IMAGE_BYTES = 5242880;

date_default_timezone_set('Asia/Tashkent');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(__DIR__ . '/runtime') && is_writable(__DIR__ . '/runtime')) {
    ini_set('error_log', __DIR__ . '/runtime/error.log');
}

set_exception_handler(function (Throwable $error): void {
    error_log('[Texnikum] ' . get_class($error) . ': ' . $error->getMessage());
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="uz"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Xizmat vaqtincha mavjud emas</title><h1>Xizmat vaqtincha mavjud emas</h1><p>Birozdan keyin qayta urinib ko‘ring. Davom etsa, sayt mas’uliga murojaat qiling.</p></html>';
});

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function config(): array {
    static $config;
    if ($config !== null) return $config;
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        exit('<!doctype html><html lang="uz"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sayt tayyorlanmoqda</title><h1>Sayt tayyorlanmoqda</h1><p>Sayt mas’uli hosting yo‘riqnomasidagi sozlash bosqichlarini yakunlashi kerak.</p></html>');
    }
    $config = require $path;
    $base = rtrim((string)($config['base_url'] ?? ''), '/');
    $parsed = parse_url($base);
    if (!is_array($config) || !is_array($parsed) || !in_array($parsed['scheme'] ?? '', ['http','https'], true)
        || empty($parsed['host']) || isset($parsed['query']) || isset($parsed['fragment']) || isset($parsed['user'])
        || preg_match('/[\r\n]/', $base) || str_contains($base, 'YOUR-DOMAIN')) {
        throw new RuntimeException('Configure a valid base_url in the private config file.');
    }
    $config['base_url'] = $base;
    return $config;
}
function url(string $path = '', array $query = []): string {
    return config()['base_url'] . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}
function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty(config()['trust_proxy_https']) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
function headers_common(bool $private = false): void {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; frame-src https://www.google.com; connect-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'");
    header($private ? 'Cache-Control: no-store, private' : 'Cache-Control: no-cache');
    if ($private) header('X-Robots-Tag: noindex, nofollow');
    if (!empty(config()['force_https']) && !is_https()) {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $basePath = rtrim((string)parse_url(config()['base_url'], PHP_URL_PATH), '/');
        $requestPath = (string)parse_url($uri, PHP_URL_PATH);
        $relative = str_starts_with($requestPath, $basePath . '/') ? substr($requestPath, strlen($basePath)) : '/';
        $query = parse_url($uri, PHP_URL_QUERY);
        $location = config()['base_url'] . $relative . ($query ? '?' . $query : '');
        if (str_starts_with($location, 'https://') && !preg_match('/[\r\n]/', $location)) {
            header('Location: ' . $location, true, 308); exit;
        }
        throw new RuntimeException('force_https requires an https base_url.');
    }
}
function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $d = config()['db'];
    $host = (string)$d['host']; $name = (string)$d['name'];
    if (preg_match('/[;\r\n]/', $host . $name)) throw new RuntimeException('Invalid database configuration.');
    $pdo = new PDO('mysql:host=' . $host . ';port=' . (int)($d['port'] ?? 3306) . ';dbname=' . $name . ';charset=utf8mb4',
        (string)$d['user'], (string)$d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $pdo->exec("SET time_zone = '+05:00'");
    return $pdo;
}
function installed(): bool {
    try { return (int)db()->query('SELECT COUNT(*) FROM tx_admins')->fetchColumn() > 0; }
    catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) === 1146) return false; throw $e; }
}
function require_installed(): void {
    if (!installed()) { http_response_code(503); header('Retry-After: 3600'); exit('Sayt tayyorlanmoqda. Sayt mas’uli o‘rnatishni yakunlashi kerak.'); }
}
function now(): string { return date('Y-m-d H:i:s'); }
function ini_bytes(string $value): int {
    $value=trim($value);if($value==='-1')return PHP_INT_MAX;
    $number=(float)$value;$suffix=strtolower(substr($value,-1));
    return (int)($number*match($suffix){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1});
}
function lang(): string {
    static $lang;
    if ($lang) return $lang;
    $requested = is_string($_GET['lang'] ?? null) ? $_GET['lang'] : '';
    $cookie = is_string($_COOKIE['site_lang'] ?? null) ? $_COOKIE['site_lang'] : '';
    $lang = array_key_exists($requested, LANGUAGES) ? $requested : (array_key_exists($cookie, LANGUAGES) ? $cookie : (config()['default_language'] ?? 'uz'));
    if (!array_key_exists($lang, LANGUAGES)) $lang = 'uz';
    if ($requested && $requested !== $cookie && array_key_exists($requested, LANGUAGES)) {
        setcookie('site_lang', $lang, ['expires' => time() + 31536000, 'path' => (parse_url(config()['base_url'], PHP_URL_PATH) ?: '') . '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    }
    return $lang;
}
function translations(): array {
    static $data;
    return $data ??= json_decode(file_get_contents(__DIR__ . '/content.json'), true, 512, JSON_THROW_ON_ERROR);
}
function t(string $key): string { return (string)(translations()[lang()][$key] ?? translations()['uz'][$key] ?? $key); }
function session_start_safe(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $directory=__DIR__.'/runtime/sessions';
    if(!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory))throw new RuntimeException('Cannot create private session directory.');
    if(!is_writable($directory))throw new RuntimeException('Private session directory is not writable.');
    ini_set('session.save_path',$directory);
    ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '1800');
    ini_set('session.gc_probability','1');ini_set('session.gc_divisor','100');
    session_name('texnikum_admin');
    session_set_cookie_params(['lifetime'=>0, 'path'=>(parse_url(config()['base_url'], PHP_URL_PATH) ?: '') . '/', 'secure'=>is_https(), 'httponly'=>true, 'samesite'=>'Strict']);
    if(!session_start())throw new RuntimeException('Cannot start an administrator session.');
}
function csrf(): string { session_start_safe(); return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function verify_csrf(): void {
    session_start_safe();
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(csrf(), $_POST['csrf'])) {
        http_response_code(403); exit('So‘rov tasdiqlanmadi. Sahifani yangilab, qayta urinib ko‘ring.');
    }
}
function redirect_admin(string $query = ''): never { header('Location: ' . url('admin/index.php') . ($query ? '?' . $query : ''), true, 303); exit; }
function current_admin(): ?array {
    session_start_safe();
    if (empty($_SESSION['admin_id'])) return null;
    if (time() - (int)($_SESSION['last_seen'] ?? 0) > 1800 || time() - (int)($_SESSION['signed_in'] ?? 0) > 28800) {
        $_SESSION = []; session_regenerate_id(true); return null;
    }
    $stmt = db()->prepare('SELECT id, username, password_hash, session_version FROM tx_admins WHERE id = ?');
    $stmt->execute([(int)$_SESSION['admin_id']]); $user = $stmt->fetch();
    if (!$user || (int)$user['session_version'] !== (int)($_SESSION['auth_version'] ?? 0)) { $_SESSION = []; return null; }
    $_SESSION['last_seen'] = time();
    return $user;
}
function require_admin(): array { $user = current_admin(); if (!$user) redirect_admin('view=login'); return $user; }
function login_allowed(): bool {
    $pdo = db(); $key = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')); $time = time();
    $pdo->beginTransaction();
    try {
        $s=$pdo->prepare('INSERT IGNORE INTO tx_login_attempts (ip_hash, attempts, window_started) VALUES (?,0,?)');$s->execute([$key,$time]);
        $s=$pdo->prepare('SELECT attempts, window_started FROM tx_login_attempts WHERE ip_hash=? FOR UPDATE');$s->execute([$key]);$row=$s->fetch();
        $fresh=$time-(int)$row['window_started']>=900;$count=$fresh?0:(int)$row['attempts'];
        if ($count>=10) {$pdo->commit();return false;}
        $s=$pdo->prepare('UPDATE tx_login_attempts SET attempts=?,window_started=? WHERE ip_hash=?');$s->execute([$count+1,$fresh?$time:(int)$row['window_started'],$key]);
        $pdo->commit(); return true;
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
}
function clear_login_attempts(): void {
    $s=db()->prepare('DELETE FROM tx_login_attempts WHERE ip_hash=? OR window_started<?');
    $s->execute([hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')),time()-86400]);
}
function password_error(string $password): ?string {
    if (mb_strlen($password, 'UTF-8') < 12 || strlen($password) > 72) return 'Parol kamida 12 belgi va ko‘pi bilan 72 bayt bo‘lsin.';
    return null;
}
function public_news(int $limit = 6, int $offset = 0): array {
    $s=db()->prepare('SELECT n.*,t.title,t.body FROM tx_news n JOIN tx_news_translations t ON t.news_id=n.id AND t.lang=? WHERE n.status=\'published\' AND n.published_at<=? ORDER BY n.published_at DESC,n.id DESC LIMIT ? OFFSET ?');
    $s->bindValue(1,lang());$s->bindValue(2,now());$s->bindValue(3,$limit,PDO::PARAM_INT);$s->bindValue(4,$offset,PDO::PARAM_INT);$s->execute();return $s->fetchAll();
}
function public_news_count(): int {
    $s=db()->prepare("SELECT COUNT(*) FROM tx_news WHERE status='published' AND published_at<=?");$s->execute([now()]);return (int)$s->fetchColumn();
}
function find_news(int $id): ?array {
    $s=db()->prepare('SELECT * FROM tx_news WHERE id=?');$s->execute([$id]);$item=$s->fetch();if(!$item)return null;
    $s=db()->prepare('SELECT lang,title,body FROM tx_news_translations WHERE news_id=?');$s->execute([$id]);
    $item['translations']=[];foreach($s as $row)$item['translations'][$row['lang']]=['title'=>$row['title'],'body'=>$row['body']];return $item;
}
function excerpt(string $text, int $length=170): string {
    $text=preg_replace('/\s+/u',' ',trim($text));return mb_strlen($text)>$length ? mb_substr($text,0,$length-1).'…' : $text;
}
function paragraphs(string $text): string {
    $parts=preg_split('/\R\s*\R/u',trim($text));return implode('',array_map(fn($p)=>'<p>'.nl2br(e($p),false).'</p>',$parts));
}
function image_url(string $path): string {
    if (!preg_match('~^(assets/img/[a-z0-9_-]+\.webp|uploads/[a-f0-9]{32}\.(webp|jpg))$~D',$path)) $path='assets/img/fon1.webp';
    return url($path);
}
function uploaded_image(array $file): string {
    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new InvalidArgumentException('Rasm yuklanmadi. Hajmni va hosting chegaralarini tekshiring.');
    $tmp=(string)$file['tmp_name'];
    if (!is_uploaded_file($tmp) || filesize($tmp)>MAX_IMAGE_BYTES) throw new InvalidArgumentException('Rasm 5 MB dan katta bo‘lmasin.');
    $info=@getimagesize($tmp);
    if (!$info || !in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)) throw new InvalidArgumentException('Faqat JPG, PNG yoki WebP rasm yuklang.');
    if ($info[0]*$info[1]>12000000 || $info[0]>12000 || $info[1]>12000) throw new InvalidArgumentException('Rasm 12 megapikseldan oshmasin. Avval o‘lchamini kichraytiring.');
    $im=match($info[2]){IMAGETYPE_JPEG=>@imagecreatefromjpeg($tmp),IMAGETYPE_PNG=>@imagecreatefrompng($tmp),IMAGETYPE_WEBP=>@imagecreatefromwebp($tmp)};
    if (!$im) throw new InvalidArgumentException('Rasm fayli buzilgan yoki qo‘llab-quvvatlanmaydi.');
    if($info[2]===IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif=@exif_read_data($tmp);$orientation=(int)($exif['Orientation']??1);
        if(in_array($orientation,[2,4,5,7],true))imageflip($im,IMG_FLIP_HORIZONTAL);
        $angle=match($orientation){3,4=>180,5,6=>-90,7,8=>90,default=>0};
        if($angle){$rotated=imagerotate($im,$angle,0);imagedestroy($im);$im=$rotated;}
    }
    $w=imagesx($im);$h=imagesy($im);$scale=min(1,1600/max($w,$h));
    $canvas=imagecreatetruecolor(max(1,(int)round($w*$scale)),max(1,(int)round($h*$scale)));
    $white=imagecolorallocate($canvas,255,255,255);imagefill($canvas,0,0,$white);
    imagecopyresampled($canvas,$im,0,0,0,0,imagesx($canvas),imagesy($canvas),$w,$h);imagedestroy($im);
    $ext=function_exists('imagewebp')?'webp':'jpg';$relative='uploads/'.bin2hex(random_bytes(16)).'.'.$ext;
    $target=(defined('PUBLIC_ROOT')?PUBLIC_ROOT:dirname(__DIR__).'/public_html').'/'.$relative;
    $ok=$ext==='webp'?imagewebp($canvas,$target,82):imagejpeg($canvas,$target,85);imagedestroy($canvas);
    if(!$ok)throw new RuntimeException('Uploads directory is not writable.');
    chmod($target,0644);return $relative;
}
function delete_uploaded_image(string $path): void {
    if(preg_match('~^uploads/[a-f0-9]{32}\.(webp|jpg)$~D',$path)) {
        $s=db()->prepare('SELECT COUNT(*) FROM tx_news WHERE image=?');$s->execute([$path]);
        if((int)$s->fetchColumn()===0){$full=(defined('PUBLIC_ROOT')?PUBLIC_ROOT:dirname(__DIR__).'/public_html').'/'.$path;if(is_file($full))@unlink($full);}
    }
}
function positive_id(mixed $value): int {
    return is_scalar($value) ? max(0,(int)filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]])) : 0;
}
