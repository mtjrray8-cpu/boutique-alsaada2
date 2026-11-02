<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$config = require __DIR__ . '/../config.php';
try {
    $pdo = new PDO(
        'mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',
        $config['db_user'], $config['db_pass'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('تعذر الاتصال بقاعدة البيانات. راجع بيانات MySQL في config.php ثم استورد database.sql.');
}
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function money(float|string $value): string { global $config; return number_format((float)$value, 2).' '.e($config['currency']); }
function setting(string $key, string $default=''): string {
    global $pdo;
    $q=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?'); $q->execute([$key]);
    $v=$q->fetchColumn(); return $v === false ? $default : (string)$v;
}
function is_admin(): bool { return !empty($_SESSION['admin_id']); }
function require_admin(): void { if (!is_admin()) { header('Location: login.php'); exit; } }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(403); exit('طلب غير صالح. أعد تحميل الصفحة.'); } }
function upload_image(array $file, string $folder='products'): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 4*1024*1024) throw new RuntimeException('حجم الصورة يجب ألا يتجاوز 4 ميجابايت.');
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($file['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('الصورة يجب أن تكون JPG أو PNG أو WEBP.');
    $dir=__DIR__.'/../uploads/'.$folder; if (!is_dir($dir)) mkdir($dir,0755,true);
    $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('تعذر حفظ الصورة.');
    return 'uploads/'.$folder.'/'.$name;
}
