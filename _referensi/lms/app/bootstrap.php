<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config/config.php');

require_once BASE_PATH . '/app/helpers.php';

if (!is_file(CONFIG_PATH)) {
    if (!defined('SETUP_MODE')) {
        header('Location: setup.php');
        exit;
    }
    return;
}

$GLOBALS['app_config'] = require CONFIG_PATH;
$appConfig = $GLOBALS['app_config']['app'] ?? [];
date_default_timezone_set((string) ($appConfig['timezone'] ?? 'Asia/Jakarta'));

ini_set('display_errors', !empty($appConfig['debug']) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) ($appConfig['session_name'] ?? 'stai_lms_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

try {
    $db = $GLOBALS['app_config']['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) ($db['port'] ?? 3306),
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );
    $GLOBALS['pdo'] = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
} catch (Throwable $exception) {
    error_log($exception->__toString());
    http_response_code(500);
    if (defined('API_MODE')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Koneksi basis data tidak tersedia.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>LMS tidak tersedia</title>';
        echo '<style>body{font:16px system-ui;background:#f4f7f5;color:#183029;padding:8vw}.box{max-width:680px;margin:auto;background:#fff;padding:32px;border-radius:20px;box-shadow:0 16px 45px #163d2d18}a{color:#08784f}</style>';
        echo '<div class="box"><h1>LMS belum dapat terhubung</h1><p>Periksa konfigurasi database atau hubungi administrator. Rincian teknis telah dicatat dengan aman.</p><p><a href="setup.php">Buka pemeriksaan instalasi</a></p></div>';
    }
    exit;
}

