<?php
declare(strict_types=1);

function db(): PDO
{
    return $GLOBALS['pdo'];
}

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['app_config'] ?? [];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function redirect(string $target): never
{
    header('Location: ' . $target);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Sesi formulir berakhir. Muat ulang halaman dan coba kembali.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $items;
}

function current_user(): ?array
{
    static $cached = false;
    static $user = null;
    if ($cached) {
        return $user;
    }
    $cached = true;
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT u.*, p.name AS program_name FROM users u LEFT JOIN programs p ON p.id=u.program_id WHERE u.id=? AND u.is_active=1 LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Silakan masuk terlebih dahulu.');
        redirect('index.php?page=login');
    }
    return $user;
}

function role_is(string ...$roles): bool
{
    $user = current_user();
    return $user && in_array($user['role'], $roles, true);
}

function require_role(string ...$roles): array
{
    $user = require_auth();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        render_error_page('Akses ditolak', 'Akun Anda tidak memiliki kewenangan untuk membuka halaman ini.');
        exit;
    }
    return $user;
}

function authenticate(string $identity, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1 LIMIT 1');
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
    audit('login', 'users', (int) $user['id'], 'Pengguna masuk ke sistem');
    return true;
}

function sign_out(): void
{
    if (current_user()) {
        audit('logout', 'users', (int) current_user()['id'], 'Pengguna keluar dari sistem');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function audit(string $action, ?string $entity = null, ?int $entityId = null, ?string $detail = null): void
{
    try {
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id,action,entity_type,entity_id,detail,ip_address,user_agent,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $stmt->execute([
            (int) ($_SESSION['user_id'] ?? 0) ?: null,
            $action,
            $entity,
            $entityId,
            $detail,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
        ]);
    } catch (Throwable $ignored) {
        error_log('Audit log gagal: ' . $ignored->getMessage());
    }
}

function notify_user(int $userId, string $title, string $message, ?string $link = null): void
{
    db()->prepare('INSERT INTO notifications (user_id,title,message,link,created_at) VALUES (?,?,?,?,NOW())')->execute([$userId, $title, $message, $link]);
}

function notify_class(int $classId, string $title, string $message, ?string $link = null): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id,title,message,link,created_at) SELECT student_id,?,?,?,NOW() FROM enrollments WHERE class_id=? AND status="active"');
    $stmt->execute([$title, $message, $link, $classId]);
}

function setting(string $key, mixed $default = null): mixed
{
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable) {
            return $default;
        }
    }
    return $settings[$key] ?? $default;
}

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');
    $stmt->execute([$key, $value]);
}

function role_label(string $role): string
{
    return [
        'super_admin' => 'Super Administrator',
        'admin' => 'Administrator Akademik',
        'dosen' => 'Dosen',
        'mahasiswa' => 'Mahasiswa',
        'kaprodi' => 'Ketua Program Studi',
        'lpm' => 'LPM/UPM',
    ][$role] ?? ucfirst($role);
}

function grade_letter(float $score): string
{
    return match (true) {
        $score >= 80 => 'A',
        $score >= 70 => 'B',
        $score >= 51 => 'C',
        $score >= 41 => 'D',
        default => 'E',
    };
}

function upload_file(string $field, string $folder = 'general'): ?array
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Berkas gagal diunggah.');
    }
    $max = (int) config('app.upload_max_mb', 20) * 1024 * 1024;
    if ((int) $file['size'] > $max) {
        throw new RuntimeException('Ukuran berkas melebihi batas.');
    }
    $allowed = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'audio/mpeg' => 'mp3',
        'video/mp4' => 'mp4',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Jenis berkas tidak diizinkan.');
    }
    $folder = preg_replace('/[^a-z0-9_-]/i', '', $folder) ?: 'general';
    $dir = BASE_PATH . '/storage/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder unggahan tidak dapat dibuat.');
    }
    $stored = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        throw new RuntimeException('Berkas gagal disimpan.');
    }
    return [
        'original_name' => basename((string) $file['name']),
        'stored_path' => $folder . '/' . $stored,
        'mime_type' => $mime,
        'size_bytes' => (int) $file['size'],
    ];
}

function class_access(int $classId, array $user): ?array
{
    $stmt = db()->prepare('SELECT c.*, co.code AS course_code, co.name AS course_name, co.credits, p.name AS program_name, ay.name AS academic_year_name, u.full_name AS lecturer_name FROM classes c JOIN courses co ON co.id=c.course_id LEFT JOIN programs p ON p.id=c.program_id LEFT JOIN academic_years ay ON ay.id=c.academic_year_id LEFT JOIN users u ON u.id=c.lecturer_id WHERE c.id=?');
    $stmt->execute([$classId]);
    $class = $stmt->fetch();
    if (!$class) {
        return null;
    }
    if (in_array($user['role'], ['super_admin', 'admin', 'lpm'], true)) {
        return $class;
    }
    if ($user['role'] === 'kaprodi' && (int) $user['program_id'] === (int) $class['program_id']) {
        return $class;
    }
    if ($user['role'] === 'dosen' && (int) $class['lecturer_id'] === (int) $user['id']) {
        return $class;
    }
    if ($user['role'] === 'mahasiswa') {
        $check = db()->prepare('SELECT 1 FROM enrollments WHERE class_id=? AND student_id=? AND status="active"');
        $check->execute([$classId, $user['id']]);
        return $check->fetchColumn() ? $class : null;
    }
    return null;
}

function render_error_page(string $title, string $message): void
{
    require_once BASE_PATH . '/app/views.php';
    render_header($title);
    echo '<section class="empty"><div class="empty-icon">!</div><h1>' . e($title) . '</h1><p>' . e($message) . '</p><a class="btn" href="index.php">Kembali ke dashboard</a></section>';
    render_footer();
}
