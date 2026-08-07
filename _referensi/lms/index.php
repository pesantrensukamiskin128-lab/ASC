<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once BASE_PATH . '/app/views.php';
require_once BASE_PATH . '/app/pages/core.php';
require_once BASE_PATH . '/app/pages/admin.php';

$page = (string) ($_GET['page'] ?? 'dashboard');

try {
    if ($page === 'login') {
        if (current_user()) {
            redirect('index.php');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $blockedUntil = (int) ($_SESSION['_login_block_until'] ?? 0);
            if ($blockedUntil > time()) {
                flash('danger', 'Terlalu banyak percobaan. Coba kembali dalam ' . (int) ceil(($blockedUntil - time()) / 60) . ' menit.');
                redirect('index.php?page=login');
            }
            $identity = trim((string) ($_POST['identity'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if (authenticate($identity, $password)) {
                unset($_SESSION['_login_failures'], $_SESSION['_login_block_until']);
                redirect('index.php');
            }
            $_SESSION['_login_failures'] = (int) ($_SESSION['_login_failures'] ?? 0) + 1;
            if ($_SESSION['_login_failures'] >= 5) {
                $_SESSION['_login_block_until'] = time() + 900;
                $_SESSION['_login_failures'] = 0;
            }
            flash('danger', 'Username/email atau kata sandi tidak sesuai.');
            redirect('index.php?page=login');
        }
        render_login_page();
        exit;
    }

    if ($page === 'logout') {
        sign_out();
        redirect('index.php?page=login');
    }

    if ($page === 'file') {
        serve_protected_file();
        exit;
    }

    $user = require_auth();
    $routes = [
        'dashboard' => 'page_dashboard',
        'my-classes' => 'page_my_classes',
        'class' => 'page_classroom',
        'calendar' => 'page_calendar',
        'notifications' => 'page_notifications',
        'profile' => 'page_profile',
        'users' => 'page_users',
        'programs' => 'page_programs',
        'courses' => 'page_courses',
        'classes' => 'page_classes_admin',
        'academic-years' => 'page_academic_years',
        'announcements' => 'page_announcements',
        'reports' => 'page_reports',
        'api-tokens' => 'page_api_tokens',
        'audit' => 'page_audit',
        'settings' => 'page_settings',
    ];
    if (!isset($routes[$page])) {
        http_response_code(404);
        render_error_page('Halaman tidak ditemukan', 'Alamat yang Anda buka tidak tersedia di LMS.');
        exit;
    }
    $routes[$page]($user);
} catch (Throwable $exception) {
    error_log($exception->__toString());
    http_response_code(500);
    render_error_page('Terjadi kendala', !empty(config('app.debug')) ? $exception->getMessage() : 'Permintaan belum dapat diproses. Silakan coba kembali atau hubungi administrator.');
}

function render_login_page(): void
{
    $institution = setting('institution_name', 'STAI Yapata Al-Jawami Bandung');
    $short = setting('institution_short_name', 'STAI Al-Jawami');
    $logo = (string) setting('logo_path', '');
    $logoVersion = $logo !== '' ? substr(hash('sha256', $logo), 0, 12) : '';
    render_header('Masuk');
    ?>
    <section class="login-page">
        <div class="login-visual">
            <div class="login-logo">
                <?php if ($logo !== ''): ?>
                    <img src="index.php?page=file&amp;type=logo&amp;v=<?= e($logoVersion) ?>" alt="Logo <?= e($institution) ?>">
                <?php else: ?>
                    <span class="brand-mark">AJ</span>
                <?php endif; ?>
                <span><b>LMS</b><small><?= e($short) ?></small></span>
            </div>
            <div class="login-copy">
                <p class="eyebrow">RUANG BELAJAR DIGITAL</p>
                <h1>Belajar terarah.<br>Terhubung. Bermakna.</h1>
                <p>Satu ruang terpadu untuk perkuliahan offline, online, dan hybrid di <?= e($institution) ?>.</p>
                <div class="login-features"><span>Materi & RPS</span><span>Presensi</span><span>Tugas & Nilai</span><span>OBE</span></div>
            </div>
            <small><?= e(setting('motto', 'Profesional – Unggul – Mandiri – Berakhlakul Karimah')) ?></small>
        </div>
        <div class="login-form-wrap">
            <div class="login-card">
                <p class="eyebrow">SELAMAT DATANG</p>
                <h2>Masuk ke akun Anda</h2>
                <p>Gunakan username atau email institusi.</p>
                <?php foreach (take_flashes() as $flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endforeach; ?>
                <form method="post" class="form-grid">
                    <?= csrf_field() ?>
                    <label>Username atau email<input name="identity" autocomplete="username" required autofocus placeholder="Masukkan username atau email"></label>
                    <label>Kata sandi<input type="password" name="password" autocomplete="current-password" required placeholder="Masukkan kata sandi"></label>
                    <button class="btn btn-block" type="submit">Masuk ke LMS</button>
                </form>
                <div class="login-help"><b>Mengalami kendala akses?</b><br>Hubungi Administrator Akademik. Kata sandi tidak pernah diminta melalui pesan pribadi.</div>
            </div>
        </div>
    </section>
    <?php
    render_footer();
}

function serve_protected_file(): void
{
    $type = (string) ($_GET['type'] ?? '');
    $isPublicLogo = $type === 'logo';
    if ($type === 'logo') {
        $path = setting('logo_path', '');
        if (!$path) {
            http_response_code(404);
            exit;
        }
        $record = ['stored_path' => $path, 'original_name' => 'logo-institusi', 'mime_type' => null];
    } else {
        $user = require_auth();
        $id = (int) ($_GET['id'] ?? 0);
        $source = (string) ($_GET['source'] ?? 'material');
        if ($source === 'material') {
            $stmt = db()->prepare('SELECT m.file_path AS stored_path,m.original_name,m.mime_type,m.class_id FROM materials m WHERE m.id=?');
        } elseif ($source === 'submission') {
            $stmt = db()->prepare('SELECT s.file_path AS stored_path,s.original_name,s.mime_type,a.class_id,s.student_id FROM submissions s JOIN assignments a ON a.id=s.assignment_id WHERE s.id=?');
        } elseif ($source === 'syllabus') {
            $stmt = db()->prepare('SELECT c.syllabus_path AS stored_path,"RPS.pdf" AS original_name,"application/pdf" AS mime_type,c.id AS class_id FROM classes c WHERE c.id=?');
        } else {
            http_response_code(404);
            exit;
        }
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        if (!$record || !class_access((int) $record['class_id'], $user)) {
            http_response_code(403);
            exit;
        }
    }
    $safe = str_replace(['../', '..\\'], '', (string) $record['stored_path']);
    $absolute = BASE_PATH . '/storage/uploads/' . $safe;
    if (!is_file($absolute)) {
        http_response_code(404);
        exit;
    }
    $mime = $record['mime_type'] ?: (new finfo(FILEINFO_MIME_TYPE))->file($absolute);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($absolute));
    header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode((string) $record['original_name']));
    header('Cache-Control: ' . ($isPublicLogo ? 'public, max-age=86400, immutable' : 'private, max-age=600'));
    readfile($absolute);
}
