<?php
declare(strict_types=1);

define('SETUP_MODE', true);
require __DIR__ . '/app/bootstrap.php';

$requirements = [
    'PHP 8.3 atau lebih baru' => version_compare(PHP_VERSION, '8.3.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'Mbstring' => extension_loaded('mbstring'),
    'Fileinfo' => extension_loaded('fileinfo'),
    'OpenSSL' => extension_loaded('openssl'),
    'Folder config dapat ditulis' => is_writable(__DIR__ . '/config'),
    'Folder storage dapat ditulis' => is_writable(__DIR__ . '/storage'),
];
$ready = !in_array(false, $requirements, true);
$installed = is_file(__DIR__ . '/config/config.php');
$error = null;
$success = false;
$demoInstalled = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    if (!$ready) {
        $error = 'Persyaratan server belum terpenuhi.';
    } else {
        $input = [
            'app_url' => rtrim(trim((string) ($_POST['app_url'] ?? '')), '/'),
            'db_host' => trim((string) ($_POST['db_host'] ?? 'localhost')),
            'db_port' => (int) ($_POST['db_port'] ?? 3306),
            'db_name' => trim((string) ($_POST['db_name'] ?? '')),
            'db_user' => trim((string) ($_POST['db_user'] ?? '')),
            'db_password' => (string) ($_POST['db_password'] ?? ''),
            'admin_name' => trim((string) ($_POST['admin_name'] ?? 'Administrator LMS')),
            'admin_username' => trim((string) ($_POST['admin_username'] ?? 'admin')),
            'admin_email' => trim((string) ($_POST['admin_email'] ?? 'admin@example.com')),
            'admin_password' => (string) ($_POST['admin_password'] ?? ''),
        ];

        if ($input['app_url'] === '' || $input['db_name'] === '' || $input['db_user'] === '' || strlen($input['admin_password']) < 10) {
            $error = 'Lengkapi semua kolom wajib. Kata sandi administrator minimal 10 karakter.';
        } elseif (!filter_var($input['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Alamat email administrator tidak valid.';
        } else {
            try {
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $input['db_host'], $input['db_port'], $input['db_name']);
                $pdo = new PDO($dsn, $input['db_user'], $input['db_password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                foreach (require __DIR__ . '/database/migrations.php' as $name => $sql) {
                    $pdo->exec($sql);
                }
                require __DIR__ . '/database/seed.php';
                $pdo->beginTransaction();
                seed_database($pdo, [
                    'username' => $input['admin_username'],
                    'email' => $input['admin_email'],
                    'password' => $input['admin_password'],
                    'full_name' => $input['admin_name'],
                ]);
                if (isset($_POST['install_demo'])) {
                    seed_demo_database($pdo);
                    $demoInstalled = true;
                }
                $pdo->commit();

                $configuration = [
                    'app' => [
                        'name' => 'LMS STAI Yapata Al-Jawami',
                        'url' => $input['app_url'],
                        'timezone' => 'Asia/Jakarta',
                        'debug' => false,
                        'session_name' => 'stai_lms_' . substr(hash('sha256', $input['app_url']), 0, 12),
                        'upload_max_mb' => 20,
                    ],
                    'database' => [
                        'host' => $input['db_host'],
                        'port' => $input['db_port'],
                        'name' => $input['db_name'],
                        'username' => $input['db_user'],
                        'password' => $input['db_password'],
                        'charset' => 'utf8mb4',
                    ],
                ];
                $contents = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($configuration, true) . ";\n";
                $temp = __DIR__ . '/config/config.php.tmp';
                if (file_put_contents($temp, $contents, LOCK_EX) === false || !rename($temp, __DIR__ . '/config/config.php')) {
                    throw new RuntimeException('Konfigurasi tidak dapat disimpan. Periksa izin folder config.');
                }
                file_put_contents(__DIR__ . '/storage/installed.lock', date(DATE_ATOM) . PHP_EOL, LOCK_EX);
                $success = true;
                $installed = true;
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log($exception->__toString());
                $error = 'Instalasi gagal: ' . $exception->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Instalasi LMS STAI Al-Jawami</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="setup-page">
<main class="setup-shell">
    <section class="setup-brand">
        <div class="brand-mark">AJ</div>
        <p class="eyebrow">INSTALASI AMAN</p>
        <h1>LMS STAI<br>Yapata Al-Jawami</h1>
        <p>Installer ini menyiapkan database, identitas awal, program studi, dan akun administrator tanpa mengharuskan Anda mengubah kode.</p>
        <div class="setup-points">
            <span>PHP 8.3</span><span>MySQL/MariaDB</span><span>cPanel Ready</span>
        </div>
    </section>
    <section class="setup-card">
        <?php if ($success): ?>
            <div class="success-symbol">✓</div>
            <p class="eyebrow">INSTALASI SELESAI</p>
            <h2>LMS siap digunakan</h2>
            <p>Masuk dengan username administrator dan kata sandi yang baru saja Anda buat. Demi keamanan, hapus atau ubah nama berkas <code>setup.php</code> setelah memastikan login berhasil.</p>
            <?php if ($demoInstalled): ?><div class="alert alert-info"><span>Data demonstrasi dipasang. Dosen: <code>demo.dosen</code>; mahasiswa: <code>2026110001</code>; kata sandi keduanya: <code>Demo!2026#</code>.</span></div><?php endif; ?>
            <a class="btn btn-block" href="index.php?page=login">Masuk ke LMS</a>
        <?php elseif ($installed): ?>
            <div class="success-symbol">✓</div>
            <h2>Aplikasi sudah terpasang</h2>
            <p>Konfigurasi aktif telah ditemukan. Installer dikunci untuk mencegah instalasi ulang.</p>
            <a class="btn btn-block" href="index.php?page=login">Buka halaman login</a>
        <?php else: ?>
            <p class="eyebrow">LANGKAH 1 DARI 1</p>
            <h2>Hubungkan dan aktifkan</h2>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <details class="requirements" <?= $ready ? '' : 'open' ?>>
                <summary>Pemeriksaan server <?= $ready ? '<b class="ok">Memenuhi</b>' : '<b class="bad">Perlu diperbaiki</b>' ?></summary>
                <ul>
                    <?php foreach ($requirements as $label => $status): ?>
                        <li class="<?= $status ? 'ok' : 'bad' ?>"><span><?= $status ? '✓' : '×' ?></span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <form method="post" class="form-grid">
                <div class="section-label">Alamat aplikasi</div>
                <label class="span-2">URL LMS<input type="url" name="app_url" required placeholder="https://lms.stai-aljawami.ac.id" value="<?= htmlspecialchars((string) ($_POST['app_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <div class="section-label">Database cPanel</div>
                <label>Host MySQL<input name="db_host" required value="<?= htmlspecialchars((string) ($_POST['db_host'] ?? 'localhost'), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Port<input type="number" name="db_port" required value="<?= (int) ($_POST['db_port'] ?? 3306) ?>"></label>
                <label>Nama database<input name="db_name" required value="<?= htmlspecialchars((string) ($_POST['db_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Pengguna database<input name="db_user" required value="<?= htmlspecialchars((string) ($_POST['db_user'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="span-2">Kata sandi database<input type="password" name="db_password" autocomplete="new-password"></label>
                <div class="section-label">Administrator pertama</div>
                <label class="span-2">Nama lengkap<input name="admin_name" required value="<?= htmlspecialchars((string) ($_POST['admin_name'] ?? 'Administrator LMS'), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Username<input name="admin_username" required value="<?= htmlspecialchars((string) ($_POST['admin_username'] ?? 'admin'), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Email<input type="email" name="admin_email" required value="<?= htmlspecialchars((string) ($_POST['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="span-2">Kata sandi administrator<input type="password" name="admin_password" minlength="10" required autocomplete="new-password"><small>Minimal 10 karakter; gunakan kombinasi huruf, angka, dan simbol.</small></label>
                <label class="span-2" style="display:flex;grid-template-columns:auto 1fr;align-items:center"><input type="checkbox" name="install_demo" style="width:auto"> Pasang data demonstrasi untuk mencoba seluruh alur LMS</label>
                <button class="btn btn-block span-2" type="submit" <?= !$ready ? 'disabled' : '' ?>>Pasang LMS Sekarang</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
