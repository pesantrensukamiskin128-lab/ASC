<?php
declare(strict_types=1);

define('API_MODE', true);
require dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$origin = (string) setting('api_allowed_origin', '');
if ($origin !== '' && ($_SERVER['HTTP_ORIGIN'] ?? '') === $origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Vary: Origin');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$apiBase = rtrim(str_replace('index.php', '', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')), '/');
$path = '/' . ltrim(substr($requestPath, strlen($apiBase)), '/');
$path = preg_replace('#^/index\.php#', '', $path) ?: '/';

try {
    if ($path === '/health') {
        api_response(['ok' => true, 'service' => 'LMS STAI Al-Jawami API', 'version' => '1.0.0', 'time' => date(DATE_ATOM)]);
    }
    $token = api_authenticate();
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === '/v1/programs') {
        api_require_scope($token, 'read');
        api_response(['ok' => true, 'data' => db()->query('SELECT id,code,name,degree,is_active,updated_at FROM programs ORDER BY code')->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === '/v1/courses') {
        api_require_scope($token, 'read');
        api_response(['ok' => true, 'data' => db()->query('SELECT c.id,c.external_id,c.program_id,p.code program_code,c.code,c.name,c.credits,c.semester_number,c.is_active,c.updated_at FROM courses c LEFT JOIN programs p ON p.id=c.program_id ORDER BY p.code,c.code')->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === '/v1/classes') {
        api_require_scope($token, 'read');
        api_response(['ok' => true, 'data' => db()->query('SELECT c.id,c.external_id,c.course_id,c.program_id,c.academic_year_id,c.lecturer_id,c.name,c.mode,c.room,c.meeting_url,c.schedule_day,c.schedule_time,c.status,c.updated_at FROM classes c ORDER BY c.id')->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === '/v1/enrollments') {
        api_require_scope($token, 'read');
        api_response(['ok' => true, 'data' => db()->query('SELECT e.id,e.external_id,e.class_id,e.student_id,u.external_id student_external_id,e.status,e.enrolled_at FROM enrollments e JOIN users u ON u.id=e.student_id ORDER BY e.id')->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === '/v1/sync/users') {
        api_require_scope($token, 'sync');
        $payload = api_json_body();
        $items = $payload['data'] ?? $payload;
        if (!is_array($items) || count($items) > 500) {
            api_error('Data harus berupa array dengan maksimal 500 pengguna.', 422);
        }
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (external_id,username,email,password_hash,full_name,identity_number,role,program_id,phone,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE username=VALUES(username),email=VALUES(email),full_name=VALUES(full_name),identity_number=VALUES(identity_number),role=VALUES(role),program_id=VALUES(program_id),phone=VALUES(phone),is_active=VALUES(is_active),updated_at=NOW()');
        $processed = 0;
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['external_id']) || empty($item['username']) || empty($item['full_name']) || empty($item['role'])) {
                throw new InvalidArgumentException('Setiap pengguna wajib memiliki external_id, username, full_name, dan role.');
            }
            if (!in_array($item['role'], ['super_admin', 'admin', 'dosen', 'mahasiswa', 'kaprodi', 'lpm'], true)) {
                throw new InvalidArgumentException('Peran pengguna tidak dikenali: ' . $item['role']);
            }
            $programId = null;
            if (!empty($item['program_code'])) {
                $program = $pdo->prepare('SELECT id FROM programs WHERE code=?');
                $program->execute([$item['program_code']]);
                $programId = $program->fetchColumn() ?: null;
            }
            $password = (string) ($item['initial_password'] ?? ('Ajw!' . random_int(100000, 999999) . '#'));
            $stmt->execute([(string) $item['external_id'], (string) $item['username'], $item['email'] ?? null, password_hash($password, PASSWORD_DEFAULT), (string) $item['full_name'], $item['identity_number'] ?? null, (string) $item['role'], $programId, $item['phone'] ?? null, array_key_exists('is_active', $item) ? (int) (bool) $item['is_active'] : 1]);
            $processed++;
        }
        $pdo->commit();
        audit('api_sync', 'users', null, 'API memproses ' . $processed . ' pengguna');
        api_response(['ok' => true, 'processed' => $processed]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === '/v1/sync/courses') {
        api_require_scope($token, 'sync');
        $items = api_sync_items();
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO courses (external_id,program_id,code,name,credits,semester_number,description,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE external_id=VALUES(external_id),program_id=VALUES(program_id),code=VALUES(code),name=VALUES(name),credits=VALUES(credits),semester_number=VALUES(semester_number),description=VALUES(description),is_active=VALUES(is_active),updated_at=NOW()');
        $processed = 0;
        foreach ($items as $item) {
            if (empty($item['external_id']) || empty($item['code']) || empty($item['name'])) {
                throw new InvalidArgumentException('Setiap mata kuliah wajib memiliki external_id, code, dan name.');
            }
            $programId = api_program_id($item['program_code'] ?? null);
            $stmt->execute([$item['external_id'], $programId, $item['code'], $item['name'], (int) ($item['credits'] ?? 2), (int) ($item['semester_number'] ?? 1), $item['description'] ?? null, array_key_exists('is_active', $item) ? (int) (bool) $item['is_active'] : 1]);
            $processed++;
        }
        $pdo->commit();
        audit('api_sync', 'courses', null, 'API memproses ' . $processed . ' mata kuliah');
        api_response(['ok' => true, 'processed' => $processed]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === '/v1/sync/classes') {
        api_require_scope($token, 'sync');
        $items = api_sync_items();
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO classes (external_id,course_id,program_id,academic_year_id,lecturer_id,name,mode,room,meeting_url,schedule_day,schedule_time,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,? ,NOW()) ON DUPLICATE KEY UPDATE course_id=VALUES(course_id),program_id=VALUES(program_id),academic_year_id=VALUES(academic_year_id),lecturer_id=VALUES(lecturer_id),name=VALUES(name),mode=VALUES(mode),room=VALUES(room),meeting_url=VALUES(meeting_url),schedule_day=VALUES(schedule_day),schedule_time=VALUES(schedule_time),status=VALUES(status),updated_at=NOW()');
        $processed = 0;
        foreach ($items as $item) {
            if (empty($item['external_id']) || empty($item['course_external_id']) || empty($item['academic_year']) || empty($item['semester']) || empty($item['name'])) {
                throw new InvalidArgumentException('Kelas wajib memiliki external_id, course_external_id, academic_year, semester, dan name.');
            }
            $find = $pdo->prepare('SELECT id,program_id FROM courses WHERE external_id=?');
            $find->execute([$item['course_external_id']]);
            $course = $find->fetch();
            if (!$course) throw new InvalidArgumentException('Mata kuliah eksternal tidak ditemukan: ' . $item['course_external_id']);
            $find = $pdo->prepare('SELECT id FROM academic_years WHERE name=? AND semester=?');
            $find->execute([$item['academic_year'], $item['semester']]);
            $yearId = $find->fetchColumn();
            if (!$yearId) throw new InvalidArgumentException('Tahun akademik tidak ditemukan: ' . $item['academic_year'] . ' ' . $item['semester']);
            $lecturerId = null;
            if (!empty($item['lecturer_external_id'])) {
                $find = $pdo->prepare('SELECT id FROM users WHERE external_id=?');
                $find->execute([$item['lecturer_external_id']]);
                $lecturerId = $find->fetchColumn() ?: null;
            }
            $mode = in_array($item['mode'] ?? '', ['offline', 'online', 'hybrid'], true) ? $item['mode'] : 'hybrid';
            $status = in_array($item['status'] ?? '', ['draft', 'active', 'archived'], true) ? $item['status'] : 'active';
            $stmt->execute([$item['external_id'], $course['id'], $course['program_id'], $yearId, $lecturerId, $item['name'], $mode, $item['room'] ?? null, $item['meeting_url'] ?? null, $item['schedule_day'] ?? null, $item['schedule_time'] ?? null, $status]);
            $processed++;
        }
        $pdo->commit();
        audit('api_sync', 'classes', null, 'API memproses ' . $processed . ' kelas');
        api_response(['ok' => true, 'processed' => $processed]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === '/v1/sync/enrollments') {
        api_require_scope($token, 'sync');
        $items = api_sync_items();
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO enrollments (external_id,class_id,student_id,status,enrolled_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE external_id=VALUES(external_id),class_id=VALUES(class_id),student_id=VALUES(student_id),status=VALUES(status)');
        $processed = 0;
        foreach ($items as $item) {
            if (empty($item['external_id']) || empty($item['class_external_id']) || empty($item['student_external_id'])) {
                throw new InvalidArgumentException('Peserta wajib memiliki external_id, class_external_id, dan student_external_id.');
            }
            $find = $pdo->prepare('SELECT id FROM classes WHERE external_id=?');
            $find->execute([$item['class_external_id']]);
            $classId = $find->fetchColumn();
            $find = $pdo->prepare('SELECT id FROM users WHERE external_id=? AND role="mahasiswa"');
            $find->execute([$item['student_external_id']]);
            $studentId = $find->fetchColumn();
            if (!$classId || !$studentId) throw new InvalidArgumentException('Kelas atau mahasiswa eksternal tidak ditemukan.');
            $status = in_array($item['status'] ?? '', ['active', 'dropped', 'completed'], true) ? $item['status'] : 'active';
            $stmt->execute([$item['external_id'], $classId, $studentId, $status]);
            $processed++;
        }
        $pdo->commit();
        audit('api_sync', 'enrollments', null, 'API memproses ' . $processed . ' peserta');
        api_response(['ok' => true, 'processed' => $processed]);
    }
    api_error('Endpoint tidak ditemukan.', 404);
} catch (InvalidArgumentException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_error($exception->getMessage(), 422);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->__toString());
    api_error(!empty(config('app.debug')) ? $exception->getMessage() : 'Permintaan API gagal diproses.', 500);
}

function api_authenticate(): array
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        api_error('Bearer token diperlukan.', 401);
    }
    $hash = hash('sha256', trim($matches[1]));
    $stmt = db()->prepare('SELECT * FROM api_tokens WHERE token_hash=? AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1');
    $stmt->execute([$hash]);
    $token = $stmt->fetch();
    if (!$token) {
        api_error('Token tidak valid atau telah kedaluwarsa.', 401);
    }
    db()->prepare('UPDATE api_tokens SET last_used_at=NOW() WHERE id=?')->execute([$token['id']]);
    return $token;
}

function api_require_scope(array $token, string $required): void
{
    $scopes = array_filter(array_map('trim', explode(',', (string) $token['scopes'])));
    if (!in_array($required, $scopes, true)) {
        api_error('Token tidak memiliki scope ' . $required . '.', 403);
    }
}

function api_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        api_error('Payload JSON tidak valid.', 400);
    }
    return $data;
}

function api_sync_items(): array
{
    $payload = api_json_body();
    $items = $payload['data'] ?? $payload;
    if (!is_array($items) || count($items) > 500) {
        api_error('Data harus berupa array dengan maksimal 500 item.', 422);
    }
    foreach ($items as $item) {
        if (!is_array($item)) {
            api_error('Setiap item sinkronisasi harus berupa objek JSON.', 422);
        }
    }
    return $items;
}

function api_program_id(mixed $code): ?int
{
    if (!$code) return null;
    $stmt = db()->prepare('SELECT id FROM programs WHERE code=?');
    $stmt->execute([(string) $code]);
    $id = $stmt->fetchColumn();
    if (!$id) throw new InvalidArgumentException('Program studi tidak ditemukan: ' . $code);
    return (int) $id;
}

function api_response(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(string $message, int $status): never
{
    api_response(['ok' => false, 'message' => $message], $status);
}
