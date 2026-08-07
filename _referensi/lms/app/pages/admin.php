<?php
declare(strict_types=1);

function admin_only(): array
{
    return require_role('super_admin', 'admin');
}

function page_users(array $user): void
{
    admin_only();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create') {
            $password = (string) $_POST['password'];
            if (strlen($password) < 10) {
                throw new RuntimeException('Kata sandi minimal 10 karakter.');
            }
            $stmt = db()->prepare('INSERT INTO users (external_id,username,email,password_hash,full_name,identity_number,role,program_id,phone,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,?,1,NOW())');
            $stmt->execute([trim((string) ($_POST['external_id'] ?? '')) ?: null, trim((string) $_POST['username']), trim((string) $_POST['email']) ?: null, password_hash($password, PASSWORD_DEFAULT), trim((string) $_POST['full_name']), trim((string) ($_POST['identity_number'] ?? '')) ?: null, $_POST['role'], (int) ($_POST['program_id'] ?? 0) ?: null, trim((string) ($_POST['phone'] ?? '')) ?: null]);
            audit('create', 'users', (int) db()->lastInsertId(), 'Membuat akun ' . $_POST['username']);
            flash('success', 'Akun pengguna berhasil dibuat.');
        } elseif ($action === 'toggle') {
            $id = (int) $_POST['id'];
            if ($id === (int) $user['id']) {
                throw new RuntimeException('Anda tidak dapat menonaktifkan akun sendiri.');
            }
            db()->prepare('UPDATE users SET is_active=1-is_active,updated_at=NOW() WHERE id=?')->execute([$id]);
            audit('toggle', 'users', $id, 'Mengubah status akun');
            flash('success', 'Status akun diperbarui.');
        } elseif ($action === 'reset') {
            $id = (int) $_POST['id'];
            $password = 'Ajw!' . random_int(100000, 999999) . '#';
            db()->prepare('UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            audit('reset_password', 'users', $id, 'Reset kata sandi oleh administrator');
            $_SESSION['_generated_password'] = $password;
            flash('warning', 'Kata sandi sementara dibuat. Salin sekarang karena tidak akan ditampilkan lagi.');
        } elseif ($action === 'import') {
            $file = $_FILES['csv_file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Pilih berkas CSV yang valid.');
            }
            $handle = fopen($file['tmp_name'], 'rb');
            $header = array_map(fn ($v) => strtolower(trim((string) $v)), fgetcsv($handle) ?: []);
            $required = ['username', 'full_name', 'role'];
            if (array_diff($required, $header)) {
                throw new RuntimeException('CSV wajib memiliki kolom username, full_name, dan role.');
            }
            $count = 0;
            $stmt = db()->prepare('INSERT INTO users (external_id,username,email,password_hash,full_name,identity_number,role,program_id,phone,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE email=VALUES(email),full_name=VALUES(full_name),identity_number=VALUES(identity_number),role=VALUES(role),program_id=VALUES(program_id),phone=VALUES(phone),updated_at=NOW()');
            while (($line = fgetcsv($handle)) !== false) {
                $row = array_combine($header, array_pad($line, count($header), ''));
                if (empty($row['username']) || empty($row['full_name'])) {
                    continue;
                }
                $programId = null;
                if (!empty($row['program_code'])) {
                    $find = db()->prepare('SELECT id FROM programs WHERE code=?');
                    $find->execute([$row['program_code']]);
                    $programId = $find->fetchColumn() ?: null;
                }
                $defaultPassword = $row['password'] ?? ('Ajw!' . preg_replace('/\D/', '', $row['identity_number'] ?? '') . '#');
                if (strlen($defaultPassword) < 10) {
                    $defaultPassword = 'Ajw!2026#' . random_int(10, 99);
                }
                $stmt->execute([$row['external_id'] ?: null, $row['username'], $row['email'] ?: null, password_hash($defaultPassword, PASSWORD_DEFAULT), $row['full_name'], $row['identity_number'] ?: null, $row['role'], $programId, $row['phone'] ?: null]);
                $count++;
            }
            fclose($handle);
            audit('import', 'users', null, 'Impor ' . $count . ' pengguna dari CSV');
            flash('success', $count . ' baris pengguna berhasil diproses.');
        }
        redirect('index.php?page=users');
    }
    $generated = $_SESSION['_generated_password'] ?? null;
    unset($_SESSION['_generated_password']);
    $users = db()->query('SELECT u.*,p.code program_code,p.name program_name FROM users u LEFT JOIN programs p ON p.id=u.program_id ORDER BY u.created_at DESC')->fetchAll();
    $programs = db()->query('SELECT * FROM programs WHERE is_active=1 ORDER BY name')->fetchAll();
    render_header('Manajemen Pengguna');
    page_heading('Manajemen Pengguna', 'Kelola akun, peran, program studi, dan status akses.', '<button class="btn btn-secondary" data-modal="import-users">Impor CSV</button><button class="btn" data-modal="add-user">+ Tambah Pengguna</button>');
    if ($generated) {
        echo '<div class="alert alert-warning">Kata sandi sementara: <code style="font-size:15px">' . e($generated) . '</code></div>';
    }
    echo '<article class="panel"><div class="panel-head"><div><h2>Daftar Pengguna</h2><p>' . count($users) . ' akun terdaftar</p></div><div class="search-box"><input class="search-input" data-filter="#users-table" placeholder="Cari nama, NIM/NIDN, peran..."></div></div><div class="table-wrap"><table class="data-table" id="users-table"><thead><tr><th>Pengguna</th><th>Identitas</th><th>Peran</th><th>Program Studi</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
    foreach ($users as $item) {
        echo '<tr><td><div class="table-title">' . e($item['full_name']) . '</div><div class="table-subtitle">' . e($item['email'] ?: $item['username']) . '</div></td><td>' . e($item['identity_number'] ?: '—') . '</td><td>' . e(role_label($item['role'])) . '</td><td>' . e($item['program_code'] ?: 'Institusi') . '</td><td>' . ($item['is_active'] ? status_badge('active') : '<span class="badge badge-archived">Nonaktif</span>') . '</td><td><div class="actions"><form method="post" data-confirm="Ubah status akun ini?">' . csrf_field() . '<input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . (int) $item['id'] . '"><button class="btn btn-secondary btn-small">' . ($item['is_active'] ? 'Nonaktifkan' : 'Aktifkan') . '</button></form><form method="post" data-confirm="Reset kata sandi akun ini?">' . csrf_field() . '<input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="' . (int) $item['id'] . '"><button class="btn btn-secondary btn-small">Reset</button></form></div></td></tr>';
    }
    echo '</tbody></table></div></article>';
    modal_start('add-user', 'Tambah Pengguna');
    echo '<form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="create"><label class="span-2">Nama lengkap<input name="full_name" required></label><label>Username<input name="username" required></label><label>Email<input type="email" name="email"></label><label>NIM/NIDN/NIP<input name="identity_number"></label><label>ID eksternal SIAKAD<input name="external_id"></label><label>Peran<select name="role" required><option value="mahasiswa">Mahasiswa</option><option value="dosen">Dosen</option><option value="kaprodi">Ketua Program Studi</option><option value="lpm">LPM/UPM</option><option value="admin">Administrator Akademik</option><option value="super_admin">Super Administrator</option></select></label><label>Program Studi<select name="program_id"><option value="">Institusi/tidak ditentukan</option>';
    foreach ($programs as $program) {
        echo '<option value="' . (int) $program['id'] . '">' . e($program['code'] . ' — ' . $program['name']) . '</option>';
    }
    echo '</select></label><label>Nomor telepon<input name="phone"></label><label>Kata sandi awal<input type="password" name="password" minlength="10" required></label><button class="btn span-2" type="submit">Buat Akun</button></form>';
    modal_end();
    modal_start('import-users', 'Impor Pengguna dari CSV');
    echo '<p>Kolom wajib: <code>username</code>, <code>full_name</code>, <code>role</code>. Kolom opsional: email, password, identity_number, program_code, phone, external_id.</p><form method="post" enctype="multipart/form-data" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="import"><label class="span-2">Berkas CSV<input type="file" name="csv_file" accept=".csv,text/csv" required></label><button class="btn span-2">Proses Impor</button></form>';
    modal_end();
    render_footer();
}

function page_programs(array $user): void
{
    admin_only();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if ($_POST['action'] === 'create') {
            db()->prepare('INSERT INTO programs (code,name,degree,color,is_active,created_at) VALUES (?,?,?,?,1,NOW())')->execute([strtoupper(trim((string) $_POST['code'])), trim((string) $_POST['name']), trim((string) $_POST['degree']), $_POST['color']]);
            audit('create', 'programs', (int) db()->lastInsertId(), 'Menambah program studi');
        } else {
            db()->prepare('UPDATE programs SET is_active=1-is_active,updated_at=NOW() WHERE id=?')->execute([(int) $_POST['id']]);
        }
        flash('success', 'Data program studi berhasil diperbarui.');
        redirect('index.php?page=programs');
    }
    $programs = db()->query('SELECT p.*,(SELECT COUNT(*) FROM users u WHERE u.program_id=p.id AND u.role="mahasiswa" AND u.is_active=1) student_count,(SELECT COUNT(*) FROM courses c WHERE c.program_id=p.id AND c.is_active=1) course_count FROM programs p ORDER BY p.name')->fetchAll();
    render_header('Program Studi');
    page_heading('Program Studi', 'Identitas program studi dan keterkaitannya dengan pengguna serta mata kuliah.', '<button class="btn" data-modal="add-program">+ Tambah Program Studi</button>');
    echo '<article class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Kode</th><th>Program Studi</th><th>Jenjang</th><th>Mahasiswa</th><th>Mata Kuliah</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
    foreach ($programs as $program) {
        echo '<tr><td><span class="badge" style="background:' . e($program['color']) . '18;color:' . e($program['color']) . '">' . e($program['code']) . '</span></td><td><b>' . e($program['name']) . '</b></td><td>' . e($program['degree']) . '</td><td>' . (int) $program['student_count'] . '</td><td>' . (int) $program['course_count'] . '</td><td>' . ($program['is_active'] ? status_badge('active') : '<span class="badge badge-archived">Nonaktif</span>') . '</td><td><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . (int) $program['id'] . '"><button class="btn btn-secondary btn-small">Ubah Status</button></form></td></tr>';
    }
    echo '</tbody></table></div></article>';
    modal_start('add-program', 'Tambah Program Studi');
    echo '<form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="create"><label>Kode<input name="code" maxlength="30" required placeholder="KPI"></label><label>Jenjang<input name="degree" value="S1" required></label><label class="span-2">Nama Program Studi<input name="name" required></label><label class="span-2">Warna Identitas<input type="color" name="color" value="#08784f"></label><button class="btn span-2">Simpan Program Studi</button></form>';
    modal_end();
    render_footer();
}

function page_courses(array $user): void
{
    require_role('super_admin', 'admin', 'kaprodi');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $programId = $user['role'] === 'kaprodi' ? (int) $user['program_id'] : ((int) ($_POST['program_id'] ?? 0) ?: null);
        db()->prepare('INSERT INTO courses (program_id,code,name,credits,semester_number,description,is_active,created_at) VALUES (?,?,?,?,?,?,1,NOW())')->execute([$programId, strtoupper(trim((string) $_POST['code'])), trim((string) $_POST['name']), (int) $_POST['credits'], (int) $_POST['semester_number'], trim((string) ($_POST['description'] ?? ''))]);
        audit('create', 'courses', (int) db()->lastInsertId(), 'Menambah mata kuliah');
        flash('success', 'Mata kuliah berhasil ditambahkan.');
        redirect('index.php?page=courses');
    }
    $where = $user['role'] === 'kaprodi' ? ' WHERE c.program_id=' . (int) $user['program_id'] : '';
    $courses = db()->query('SELECT c.*,p.code program_code,p.name program_name,(SELECT COUNT(*) FROM classes cl WHERE cl.course_id=c.id) class_count,(SELECT COUNT(*) FROM course_outcomes o WHERE o.course_id=c.id) outcome_count FROM courses c LEFT JOIN programs p ON p.id=c.program_id' . $where . ' ORDER BY p.code,c.semester_number,c.name')->fetchAll();
    $programs = db()->query('SELECT * FROM programs WHERE is_active=1 ORDER BY name')->fetchAll();
    render_header('Mata Kuliah');
    page_heading('Mata Kuliah & Kurikulum', 'Kelola mata kuliah, SKS, semester, dan fondasi CPMK.', '<button class="btn" data-modal="add-course">+ Tambah Mata Kuliah</button>');
    echo '<article class="panel"><div class="panel-head"><div><h2>Daftar Mata Kuliah</h2><p>' . count($courses) . ' mata kuliah tersedia</p></div><div class="search-box"><input class="search-input" data-filter="#courses-table" placeholder="Cari kode atau nama..."></div></div><div class="table-wrap"><table class="data-table" id="courses-table"><thead><tr><th>Kode</th><th>Mata Kuliah</th><th>Program Studi</th><th>Semester</th><th>SKS</th><th>Kelas</th><th>CPMK</th></tr></thead><tbody>';
    foreach ($courses as $course) {
        echo '<tr><td><b>' . e($course['code']) . '</b></td><td><div class="table-title">' . e($course['name']) . '</div><div class="table-subtitle">' . e(mb_strimwidth((string) $course['description'], 0, 80, '…')) . '</div></td><td>' . e($course['program_code'] ?: 'Umum') . '</td><td>' . e($course['semester_number'] ?: '—') . '</td><td>' . (int) $course['credits'] . '</td><td>' . (int) $course['class_count'] . '</td><td>' . (int) $course['outcome_count'] . '</td></tr>';
    }
    echo '</tbody></table></div></article>';
    modal_start('add-course', 'Tambah Mata Kuliah');
    echo '<form method="post" class="form-grid">' . csrf_field() . '<label>Kode mata kuliah<input name="code" required></label><label>Program Studi<select name="program_id" ' . ($user['role'] === 'kaprodi' ? 'disabled' : '') . '><option value="">Mata kuliah umum</option>';
    foreach ($programs as $program) {
        echo '<option value="' . (int) $program['id'] . '" ' . ((int) $user['program_id'] === (int) $program['id'] ? 'selected' : '') . '>' . e($program['code'] . ' — ' . $program['name']) . '</option>';
    }
    echo '</select></label><label class="span-2">Nama mata kuliah<input name="name" required></label><label>Bobot SKS<input type="number" name="credits" min="1" max="12" value="2" required></label><label>Semester rekomendasi<input type="number" name="semester_number" min="1" max="14" value="1"></label><label class="span-2">Deskripsi<textarea name="description"></textarea></label><button class="btn span-2">Simpan Mata Kuliah</button></form>';
    modal_end();
    render_footer();
}

function page_classes_admin(array $user): void
{
    require_role('super_admin', 'admin', 'kaprodi');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) $_POST['action'];
        if ($action === 'create') {
            $programId = $user['role'] === 'kaprodi' ? (int) $user['program_id'] : ((int) $_POST['program_id'] ?: null);
            db()->prepare('INSERT INTO classes (course_id,program_id,academic_year_id,lecturer_id,name,mode,room,meeting_url,schedule_day,schedule_time,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,? ,"active",NOW())')->execute([(int) $_POST['course_id'], $programId, (int) $_POST['academic_year_id'], (int) $_POST['lecturer_id'] ?: null, trim((string) $_POST['name']), $_POST['mode'], trim((string) ($_POST['room'] ?? '')), trim((string) ($_POST['meeting_url'] ?? '')), trim((string) ($_POST['schedule_day'] ?? '')), trim((string) ($_POST['schedule_time'] ?? ''))]);
            audit('create', 'classes', (int) db()->lastInsertId(), 'Membuat kelas pembelajaran');
            flash('success', 'Kelas pembelajaran berhasil dibuat.');
        } elseif ($action === 'enroll') {
            $classId = (int) $_POST['class_id'];
            $check = db()->prepare('SELECT program_id FROM classes WHERE id=?');
            $check->execute([$classId]);
            $programId = $check->fetchColumn();
            if ($user['role'] === 'kaprodi' && (int) $programId !== (int) $user['program_id']) {
                throw new RuntimeException('Kelas berada di luar program studi Anda.');
            }
            db()->beginTransaction();
            db()->prepare('UPDATE enrollments SET status="dropped" WHERE class_id=?')->execute([$classId]);
            $stmt = db()->prepare('INSERT INTO enrollments (class_id,student_id,status,enrolled_at) VALUES (?,? ,"active",NOW()) ON DUPLICATE KEY UPDATE status="active"');
            $count = 0;
            foreach ($_POST['student_ids'] ?? [] as $studentId) {
                $stmt->execute([$classId, (int) $studentId]);
                $count++;
            }
            db()->commit();
            audit('enroll', 'classes', $classId, 'Mendaftarkan ' . $count . ' mahasiswa');
            flash('success', $count . ' mahasiswa berhasil dimasukkan ke kelas.');
        } elseif ($action === 'archive') {
            db()->prepare('UPDATE classes SET status=IF(status="active","archived","active"),updated_at=NOW() WHERE id=?')->execute([(int) $_POST['class_id']]);
            flash('success', 'Status kelas diperbarui.');
        }
        redirect('index.php?page=classes');
    }
    $where = $user['role'] === 'kaprodi' ? ' WHERE c.program_id=' . (int) $user['program_id'] : '';
    $classes = db()->query('SELECT c.*,co.code course_code,co.name course_name,p.code program_code,ay.name year_name,ay.semester,u.full_name lecturer_name,(SELECT COUNT(*) FROM enrollments e WHERE e.class_id=c.id AND e.status="active") student_count,(SELECT COUNT(*) FROM class_meetings m WHERE m.class_id=c.id) meeting_count FROM classes c JOIN courses co ON co.id=c.course_id LEFT JOIN programs p ON p.id=c.program_id LEFT JOIN academic_years ay ON ay.id=c.academic_year_id LEFT JOIN users u ON u.id=c.lecturer_id' . $where . ' ORDER BY ay.is_active DESC,co.name,c.name')->fetchAll();
    $courseWhere = $user['role'] === 'kaprodi' ? ' AND c.program_id=' . (int) $user['program_id'] : '';
    $courses = db()->query('SELECT c.*,p.code program_code FROM courses c LEFT JOIN programs p ON p.id=c.program_id WHERE c.is_active=1' . $courseWhere . ' ORDER BY p.code,c.name')->fetchAll();
    $programs = db()->query('SELECT * FROM programs WHERE is_active=1 ORDER BY name')->fetchAll();
    $years = db()->query('SELECT * FROM academic_years ORDER BY is_active DESC,name DESC')->fetchAll();
    $lecturerWhere = $user['role'] === 'kaprodi' ? ' AND (program_id=' . (int) $user['program_id'] . ' OR program_id IS NULL)' : '';
    $lecturers = db()->query('SELECT id,full_name,identity_number FROM users WHERE role IN ("dosen","kaprodi") AND is_active=1' . $lecturerWhere . ' ORDER BY full_name')->fetchAll();
    $students = db()->query('SELECT id,full_name,identity_number,program_id FROM users WHERE role="mahasiswa" AND is_active=1 ORDER BY full_name')->fetchAll();
    render_header('Kelas & Peserta');
    page_heading('Kelas & Peserta', 'Buka kelas semester, tentukan dosen, dan masukkan peserta.', '<button class="btn" data-modal="add-class">+ Buka Kelas</button>');
    echo '<div class="course-grid">';
    foreach ($classes as $class) {
        echo '<article class="course-card" style="--course-color:var(--primary)"><div class="course-accent"></div><div class="course-body"><div class="course-code">' . e($class['course_code']) . ' · ' . e($class['program_code'] ?: 'Umum') . '</div><h3>' . e($class['course_name']) . ' — ' . e($class['name']) . '</h3><div class="course-meta"><span>' . e($class['lecturer_name'] ?: 'Dosen belum ditentukan') . '</span><span>' . e($class['student_count']) . ' mahasiswa</span><span>' . e($class['meeting_count']) . ' pertemuan</span></div></div><div class="course-footer"><a class="btn btn-secondary btn-small" href="index.php?page=class&id=' . (int) $class['id'] . '">Buka Kelas</a><button class="btn btn-small" data-modal="enroll-' . (int) $class['id'] . '">Kelola Peserta</button></div></article>';
        modal_start('enroll-' . (int) $class['id'], 'Peserta Kelas ' . $class['name']);
        $stmt = db()->prepare('SELECT student_id FROM enrollments WHERE class_id=? AND status="active"');
        $stmt->execute([$class['id']]);
        $enrolled = array_map('intval', array_column($stmt->fetchAll(), 'student_id'));
        echo '<form method="post"><input type="hidden" name="action" value="enroll"><input type="hidden" name="class_id" value="' . (int) $class['id'] . '">' . csrf_field() . '<div class="search-box" style="margin-bottom:12px"><input class="search-input" data-filter="#student-list-' . (int) $class['id'] . '" placeholder="Cari mahasiswa..."></div><div class="table-wrap" style="max-height:430px"><table class="data-table" id="student-list-' . (int) $class['id'] . '"><tbody>';
        foreach ($students as $student) {
            if ($user['role'] === 'kaprodi' && (int) $student['program_id'] !== (int) $user['program_id']) {
                continue;
            }
            echo '<tr><td><input type="checkbox" name="student_ids[]" value="' . (int) $student['id'] . '" ' . (in_array((int) $student['id'], $enrolled, true) ? 'checked' : '') . '></td><td><b>' . e($student['full_name']) . '</b><small style="display:block">' . e($student['identity_number']) . '</small></td></tr>';
        }
        echo '</tbody></table></div><div class="form-actions"><button class="btn">Simpan Peserta</button></div></form>';
        modal_end();
    }
    if (!$classes) {
        echo '<article class="panel" style="grid-column:1/-1">';
        empty_state('Belum ada kelas', 'Buka kelas pembelajaran untuk semester yang aktif.');
        echo '</article>';
    }
    echo '</div>';
    modal_start('add-class', 'Buka Kelas Pembelajaran');
    echo '<form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="create"><label class="span-2">Mata Kuliah<select name="course_id" required><option value="">Pilih mata kuliah</option>';
    foreach ($courses as $course) {
        echo '<option value="' . (int) $course['id'] . '">' . e(($course['program_code'] ?: 'UMUM') . ' · ' . $course['code'] . ' — ' . $course['name']) . '</option>';
    }
    echo '</select></label><label>Program Studi<select name="program_id" ' . ($user['role'] === 'kaprodi' ? 'disabled' : '') . '>';
    foreach ($programs as $program) {
        echo '<option value="' . (int) $program['id'] . '" ' . ((int) $user['program_id'] === (int) $program['id'] ? 'selected' : '') . '>' . e($program['code']) . '</option>';
    }
    echo '</select></label><label>Tahun Akademik<select name="academic_year_id" required>';
    foreach ($years as $year) {
        echo '<option value="' . (int) $year['id'] . '" ' . ($year['is_active'] ? 'selected' : '') . '>' . e($year['name'] . ' · ' . ucfirst($year['semester'])) . '</option>';
    }
    echo '</select></label><label class="span-2">Dosen Pengampu<select name="lecturer_id"><option value="">Belum ditentukan</option>';
    foreach ($lecturers as $lecturer) {
        echo '<option value="' . (int) $lecturer['id'] . '">' . e($lecturer['full_name'] . ' · ' . $lecturer['identity_number']) . '</option>';
    }
    echo '</select></label><label>Nama kelas<input name="name" required value="A"></label><label>Moda<select name="mode"><option value="offline">Offline</option><option value="online">Online</option><option value="hybrid" selected>Hybrid</option></select></label><label>Hari<input name="schedule_day" placeholder="Sabtu"></label><label>Jam<input name="schedule_time" placeholder="08.00–10.30 WIB"></label><label>Ruangan<input name="room"></label><label>Tautan Meet/Zoom<input type="url" name="meeting_url"></label><button class="btn span-2">Buka Kelas</button></form>';
    modal_end();
    render_footer();
}

function page_academic_years(array $user): void
{
    admin_only();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if ($_POST['action'] === 'create') {
            $pdo = db();
            $pdo->beginTransaction();
            if (isset($_POST['is_active'])) {
                $pdo->exec('UPDATE academic_years SET is_active=0');
            }
            $pdo->prepare('INSERT INTO academic_years (name,semester,starts_on,ends_on,is_active,created_at) VALUES (?,?,?,?,?,NOW())')->execute([trim((string) $_POST['name']), $_POST['semester'], $_POST['starts_on'] ?: null, $_POST['ends_on'] ?: null, isset($_POST['is_active']) ? 1 : 0]);
            $pdo->commit();
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->exec('UPDATE academic_years SET is_active=0');
            $pdo->prepare('UPDATE academic_years SET is_active=1 WHERE id=?')->execute([(int) $_POST['id']]);
            $pdo->commit();
        }
        audit('update', 'academic_years', null, 'Memperbarui tahun akademik');
        flash('success', 'Tahun akademik berhasil diperbarui.');
        redirect('index.php?page=academic-years');
    }
    $years = db()->query('SELECT ay.*,(SELECT COUNT(*) FROM classes c WHERE c.academic_year_id=ay.id) class_count FROM academic_years ay ORDER BY ay.name DESC,FIELD(ay.semester,"ganjil","genap","pendek")')->fetchAll();
    render_header('Tahun Akademik');
    page_heading('Tahun Akademik', 'Atur periode semester yang digunakan oleh kelas pembelajaran.', '<button class="btn" data-modal="add-year">+ Tambah Periode</button>');
    echo '<article class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Tahun Akademik</th><th>Semester</th><th>Periode</th><th>Kelas</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
    foreach ($years as $year) {
        echo '<tr><td><b>' . e($year['name']) . '</b></td><td>' . e(ucfirst($year['semester'])) . '</td><td>' . e($year['starts_on'] ? date('d M Y', strtotime($year['starts_on'])) : '—') . ' – ' . e($year['ends_on'] ? date('d M Y', strtotime($year['ends_on'])) : '—') . '</td><td>' . (int) $year['class_count'] . '</td><td>' . ($year['is_active'] ? '<span class="badge badge-active">Semester Aktif</span>' : '<span class="badge">Arsip</span>') . '</td><td>' . (!$year['is_active'] ? '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="' . (int) $year['id'] . '"><button class="btn btn-secondary btn-small">Aktifkan</button></form>' : '—') . '</td></tr>';
    }
    echo '</tbody></table></div></article>';
    modal_start('add-year', 'Tambah Tahun Akademik');
    echo '<form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="create"><label>Tahun Akademik<input name="name" required placeholder="2026/2027"></label><label>Semester<select name="semester"><option value="ganjil">Ganjil</option><option value="genap">Genap</option><option value="pendek">Pendek</option></select></label><label>Tanggal mulai<input type="date" name="starts_on"></label><label>Tanggal selesai<input type="date" name="ends_on"></label><label class="span-2" style="display:flex;grid-template-columns:auto 1fr;align-items:center"><input type="checkbox" name="is_active" style="width:auto"> Jadikan semester aktif</label><button class="btn span-2">Simpan Periode</button></form>';
    modal_end();
    render_footer();
}

function page_announcements(array $user): void
{
    admin_only();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $audience = $_POST['audience_role'] ?: null;
        db()->prepare('INSERT INTO announcements (class_id,title,body,audience_role,is_pinned,published_at,expires_at,created_by) VALUES (NULL,?,?,?,?,NOW(),?,?)')->execute([trim((string) $_POST['title']), trim((string) $_POST['body']), $audience, isset($_POST['is_pinned']) ? 1 : 0, $_POST['expires_at'] ?: null, $user['id']]);
        if ($audience) {
            $stmt = db()->prepare('INSERT INTO notifications (user_id,title,message,link,created_at) SELECT id,?,?,"index.php",NOW() FROM users WHERE role=? AND is_active=1');
            $stmt->execute([trim((string) $_POST['title']), mb_strimwidth(trim((string) $_POST['body']), 0, 180, '…'), $audience]);
        } else {
            $stmt = db()->prepare('INSERT INTO notifications (user_id,title,message,link,created_at) SELECT id,?,?,"index.php",NOW() FROM users WHERE is_active=1');
            $stmt->execute([trim((string) $_POST['title']), mb_strimwidth(trim((string) $_POST['body']), 0, 180, '…')]);
        }
        audit('create', 'announcements', (int) db()->lastInsertId(), 'Menerbitkan pengumuman institusi');
        flash('success', 'Pengumuman berhasil diterbitkan.');
        redirect('index.php?page=announcements');
    }
    $items = db()->query('SELECT a.*,u.full_name FROM announcements a LEFT JOIN users u ON u.id=a.created_by ORDER BY a.published_at DESC')->fetchAll();
    render_header('Pengumuman');
    page_heading('Pengumuman Institusi', 'Informasi yang ditampilkan pada dashboard pengguna.', '<button class="btn" data-modal="add-announcement">+ Buat Pengumuman</button>');
    echo '<article class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Pengumuman</th><th>Audiens</th><th>Diterbitkan</th><th>Berakhir</th><th>Prioritas</th></tr></thead><tbody>';
    foreach ($items as $item) {
        echo '<tr><td><div class="table-title">' . e($item['title']) . '</div><div class="table-subtitle">' . e(mb_strimwidth(strip_tags($item['body']), 0, 120, '…')) . '</div></td><td>' . e($item['audience_role'] ? role_label($item['audience_role']) : 'Semua pengguna') . '</td><td>' . e(date('d M Y, H:i', strtotime($item['published_at']))) . '</td><td>' . e($item['expires_at'] ? date('d M Y', strtotime($item['expires_at'])) : 'Tidak dibatasi') . '</td><td>' . ($item['is_pinned'] ? '<span class="badge badge-active">Disematkan</span>' : '<span class="badge">Normal</span>') . '</td></tr>';
    }
    echo '</tbody></table></div></article>';
    modal_start('add-announcement', 'Buat Pengumuman');
    echo '<form method="post" class="form-grid">' . csrf_field() . '<label class="span-2">Judul<input name="title" required></label><label class="span-2">Isi pengumuman<textarea name="body" required></textarea></label><label>Audiens<select name="audience_role"><option value="">Semua pengguna</option><option value="mahasiswa">Mahasiswa</option><option value="dosen">Dosen</option><option value="kaprodi">Ketua Program Studi</option><option value="lpm">LPM/UPM</option><option value="admin">Administrator</option></select></label><label>Tanggal berakhir<input type="datetime-local" name="expires_at"></label><label class="span-2" style="display:flex;grid-template-columns:auto 1fr;align-items:center"><input type="checkbox" name="is_pinned" style="width:auto"> Sematkan sebagai informasi penting</label><button class="btn span-2">Terbitkan</button></form>';
    modal_end();
    render_footer();
}

function page_reports(array $user): void
{
    require_role('super_admin', 'admin', 'kaprodi', 'lpm');
    $programFilter = $user['role'] === 'kaprodi' ? ' AND c.program_id=' . (int) $user['program_id'] : '';
    if (($_GET['export'] ?? '') === 'csv') {
        $rows = db()->query('SELECT p.code program,co.code course_code,co.name course,c.name class_name,u.full_name lecturer,(SELECT COUNT(*) FROM enrollments e WHERE e.class_id=c.id AND e.status="active") students,(SELECT COUNT(*) FROM class_meetings m WHERE m.class_id=c.id) meetings,c.syllabus_status FROM classes c JOIN courses co ON co.id=c.course_id LEFT JOIN programs p ON p.id=c.program_id LEFT JOIN users u ON u.id=c.lecturer_id WHERE 1=1' . $programFilter . ' ORDER BY p.code,co.name')->fetchAll();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="laporan-monitoring-lms-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Program Studi', 'Kode MK', 'Mata Kuliah', 'Kelas', 'Dosen', 'Mahasiswa', 'Pertemuan', 'Status RPS']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
    $classCount = (int) db()->query('SELECT COUNT(*) FROM classes c WHERE c.status="active"' . $programFilter)->fetchColumn();
    $meetingCount = (int) db()->query('SELECT COUNT(*) FROM class_meetings m JOIN classes c ON c.id=m.class_id WHERE 1=1' . $programFilter)->fetchColumn();
    $rpsApproved = (int) db()->query('SELECT COUNT(*) FROM classes c WHERE c.syllabus_status="approved"' . $programFilter)->fetchColumn();
    $journals = (int) db()->query('SELECT COUNT(*) FROM class_meetings m JOIN classes c ON c.id=m.class_id WHERE m.journal IS NOT NULL AND m.journal<>""' . $programFilter)->fetchColumn();
    $attendance = db()->query('SELECT COUNT(*) total,SUM(a.status IN ("hadir","terlambat")) present FROM attendance a JOIN class_meetings m ON m.id=a.meeting_id JOIN classes c ON c.id=m.class_id WHERE 1=1' . $programFilter)->fetch();
    $attendanceRate = (int) $attendance['total'] ? round((int) $attendance['present'] / (int) $attendance['total'] * 100) : 0;
    $classes = db()->query('SELECT c.*,p.code program_code,co.code course_code,co.name course_name,u.full_name lecturer_name,(SELECT COUNT(*) FROM enrollments e WHERE e.class_id=c.id AND e.status="active") student_count,(SELECT COUNT(*) FROM class_meetings m WHERE m.class_id=c.id) meeting_count,(SELECT COUNT(*) FROM assignments a WHERE a.class_id=c.id) assignment_count FROM classes c JOIN courses co ON co.id=c.course_id LEFT JOIN programs p ON p.id=c.program_id LEFT JOIN users u ON u.id=c.lecturer_id WHERE 1=1' . $programFilter . ' ORDER BY p.code,co.name')->fetchAll();
    render_header('Laporan & Mutu');
    page_heading('Laporan & Mutu Pembelajaran', 'Monitoring pelaksanaan, dokumentasi, dan ketercapaian aktivitas kelas.', '<a class="btn btn-secondary" href="index.php?page=reports&export=csv">Unduh CSV</a>');
    echo '<section class="stats-grid">' . stat_card('Kelas Aktif', $classCount, active_academic_year()) . stat_card('Realisasi Pertemuan', $meetingCount, 'Target ' . ($classCount * 16), 'blue') . stat_card('Kelengkapan Jurnal', $meetingCount ? round($journals / $meetingCount * 100) . '%' : '0%', $journals . ' jurnal terisi', 'purple') . stat_card('Tingkat Kehadiran', $attendanceRate . '%', 'Hadir + terlambat', 'gold') . '</section>';
    echo '<article class="panel"><div class="panel-head"><div><h2>Monitoring Kelas</h2><p>Ringkasan indikator utama setiap kelas</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Kelas</th><th>Dosen</th><th>Mahasiswa</th><th>Pertemuan</th><th>Asesmen</th><th>RPS</th><th>Tindak Lanjut</th></tr></thead><tbody>';
    foreach ($classes as $class) {
        $risk = (int) $class['meeting_count'] < 4 ? 'Perlu monitoring' : ((int) $class['assignment_count'] === 0 ? 'Asesmen belum ada' : 'Baik');
        echo '<tr><td><a href="index.php?page=class&id=' . (int) $class['id'] . '"><div class="table-title">' . e($class['course_code'] . ' · ' . $class['course_name']) . '</div><div class="table-subtitle">' . e($class['program_code'] . ' · Kelas ' . $class['name']) . '</div></a></td><td>' . e($class['lecturer_name'] ?: '—') . '</td><td>' . (int) $class['student_count'] . '</td><td>' . (int) $class['meeting_count'] . '/16</td><td>' . (int) $class['assignment_count'] . '</td><td>' . status_badge($class['syllabus_status']) . '</td><td><span class="badge ' . ($risk === 'Baik' ? 'badge-active' : 'badge-revision') . '">' . e($risk) . '</span></td></tr>';
    }
    echo '</tbody></table></div></article>';
    render_footer();
}

function page_api_tokens(array $user): void
{
    admin_only();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if ($_POST['action'] === 'create') {
            $raw = 'stai_' . bin2hex(random_bytes(28));
            db()->prepare('INSERT INTO api_tokens (user_id,name,token_hash,scopes,expires_at,created_at) VALUES (?,?,?,?,?,NOW())')->execute([$user['id'], trim((string) $_POST['name']), hash('sha256', $raw), implode(',', $_POST['scopes'] ?? ['read']), $_POST['expires_at'] ?: null]);
            $_SESSION['_raw_api_token'] = $raw;
            audit('create', 'api_tokens', (int) db()->lastInsertId(), 'Membuat token API');
            flash('warning', 'Token baru dibuat. Salin sekarang karena tidak akan ditampilkan lagi.');
        } elseif ($_POST['action'] === 'revoke') {
            db()->prepare('DELETE FROM api_tokens WHERE id=?')->execute([(int) $_POST['id']]);
            audit('delete', 'api_tokens', (int) $_POST['id'], 'Mencabut token API');
            flash('success', 'Token API telah dicabut.');
        }
        redirect('index.php?page=api-tokens');
    }
    $raw = $_SESSION['_raw_api_token'] ?? null;
    unset($_SESSION['_raw_api_token']);
    $tokens = db()->query('SELECT t.*,u.full_name FROM api_tokens t JOIN users u ON u.id=t.user_id ORDER BY t.created_at DESC')->fetchAll();
    render_header('Integrasi API');
    page_heading('Integrasi API SIAKAD', 'Token terkontrol untuk sinkronisasi data pengguna, mata kuliah, kelas, dan peserta.', '<button class="btn" data-modal="add-token">+ Buat Token</button>');
    if ($raw) {
        echo '<div class="alert alert-warning"><span>Token API: <code style="font-size:13px;word-break:break-all">' . e($raw) . '</code></span></div>';
    }
    echo '<section class="grid-2"><article class="panel"><div class="panel-head"><div><h2>Token Aktif</h2><p>Rahasia token disimpan dalam bentuk hash</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Nama</th><th>Scope</th><th>Terakhir Digunakan</th><th>Berakhir</th><th>Aksi</th></tr></thead><tbody>';
    foreach ($tokens as $token) {
        echo '<tr><td><b>' . e($token['name']) . '</b><small style="display:block">' . e($token['full_name']) . '</small></td><td>' . e($token['scopes']) . '</td><td>' . e($token['last_used_at'] ? date('d M Y, H:i', strtotime($token['last_used_at'])) : 'Belum pernah') . '</td><td>' . e($token['expires_at'] ? date('d M Y', strtotime($token['expires_at'])) : 'Tidak dibatasi') . '</td><td><form method="post" data-confirm="Cabut token ini? Integrasi yang memakainya akan berhenti.">' . csrf_field() . '<input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="' . (int) $token['id'] . '"><button class="btn btn-danger btn-small">Cabut</button></form></td></tr>';
    }
    if (!$tokens) {
        echo '<tr><td colspan="5">Belum ada token API.</td></tr>';
    }
    echo '</tbody></table></div></article><aside><article class="panel"><div class="panel-head"><h2>Endpoint Tersedia</h2></div><div class="panel-body metric-list"><div class="metric-row"><span>Health check</span><code>GET /api/health</code></div><div class="metric-row"><span>Program studi</span><code>GET /api/v1/programs</code></div><div class="metric-row"><span>Mata kuliah</span><code>GET /api/v1/courses</code></div><div class="metric-row"><span>Kelas</span><code>GET /api/v1/classes</code></div><div class="metric-row"><span>Peserta</span><code>GET /api/v1/enrollments</code></div><div class="metric-row"><span>Sinkron pengguna</span><code>POST /api/v1/sync/users</code></div><div class="metric-row"><span>Sinkron mata kuliah</span><code>POST /api/v1/sync/courses</code></div><div class="metric-row"><span>Sinkron kelas</span><code>POST /api/v1/sync/classes</code></div><div class="metric-row"><span>Sinkron peserta</span><code>POST /api/v1/sync/enrollments</code></div></div></article><div class="alert alert-info">Dokumentasi format data tersedia dalam berkas <b>API.md</b> pada paket instalasi.</div></aside></section>';
    modal_start('add-token', 'Buat Token API');
    echo '<form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="create"><label class="span-2">Nama integrasi<input name="name" required placeholder="Sinkronisasi SIAKAD Produksi"></label><div class="span-2"><b style="font-size:12px">Hak akses</b><div style="display:flex;gap:15px;margin-top:8px"><label style="display:flex;grid-template-columns:auto 1fr"><input type="checkbox" name="scopes[]" value="read" checked style="width:auto"> Baca data</label><label style="display:flex;grid-template-columns:auto 1fr"><input type="checkbox" name="scopes[]" value="sync" style="width:auto"> Sinkronisasi</label></div></div><label class="span-2">Tanggal kedaluwarsa<input type="datetime-local" name="expires_at"><small>Kosongkan hanya untuk integrasi server yang permanen dan terlindungi.</small></label><button class="btn span-2">Buat Token</button></form>';
    modal_end();
    render_footer();
}

function page_audit(array $user): void
{
    admin_only();
    $logs = db()->query('SELECT a.*,u.full_name,u.username FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 500')->fetchAll();
    render_header('Audit Aktivitas');
    page_heading('Audit Aktivitas', 'Jejak tindakan penting untuk keamanan dan akuntabilitas akademik.');
    echo '<article class="panel"><div class="panel-head"><div><h2>500 Aktivitas Terakhir</h2><p>Login, perubahan data, presensi, tugas, dan penilaian</p></div><div class="search-box"><input class="search-input" data-filter="#audit-table" placeholder="Cari aktivitas..."></div></div><div class="table-wrap"><table class="data-table" id="audit-table"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Rincian</th><th>IP</th></tr></thead><tbody>';
    foreach ($logs as $log) {
        echo '<tr><td>' . e(date('d M Y, H:i:s', strtotime($log['created_at']))) . '</td><td><b>' . e($log['full_name'] ?: 'Sistem') . '</b><small style="display:block">' . e($log['username']) . '</small></td><td><span class="badge">' . e($log['action']) . '</span></td><td>' . e($log['entity_type'] ?: '—') . ($log['entity_id'] ? ' #' . (int) $log['entity_id'] : '') . '</td><td>' . e($log['detail'] ?: '—') . '</td><td><code>' . e($log['ip_address']) . '</code></td></tr>';
    }
    echo '</tbody></table></div></article>';
    render_footer();
}

function page_settings(array $user): void
{
    admin_only();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $fields = ['institution_name', 'institution_short_name', 'motto', 'address', 'website', 'email', 'primary_color', 'secondary_color', 'attendance_weight', 'assignment_weight', 'exam_weight', 'api_allowed_origin'];
        foreach ($fields as $field) {
            save_setting($field, trim((string) ($_POST[$field] ?? '')));
        }
        $logo = upload_file('logo', 'branding');
        if ($logo) {
            save_setting('logo_path', $logo['stored_path']);
        }
        audit('update', 'settings', null, 'Memperbarui pengaturan institusi');
        flash('success', 'Pengaturan institusi berhasil disimpan.');
        redirect('index.php?page=settings');
    }
    render_header('Pengaturan Institusi');
    page_heading('Pengaturan Institusi', 'Identitas, warna, aturan nilai, serta akses integrasi LMS.');
    echo '<article class="panel"><div class="panel-head"><div><h2>Konfigurasi Utama</h2><p>Perubahan diterapkan pada seluruh halaman LMS</p></div></div><div class="panel-body"><form method="post" enctype="multipart/form-data" class="form-grid">' . csrf_field() . '<div class="section-label">Identitas Institusi</div><label class="span-2">Nama institusi<input name="institution_name" required value="' . e(setting('institution_name')) . '"></label><label>Nama singkat<input name="institution_short_name" required value="' . e(setting('institution_short_name')) . '"></label><label>Email<input type="email" name="email" value="' . e(setting('email')) . '"></label><label class="span-2">Moto<textarea name="motto">' . e(setting('motto')) . '</textarea></label><label class="span-2">Alamat<textarea name="address">' . e(setting('address')) . '</textarea></label><label>Website<input name="website" value="' . e(setting('website')) . '"></label><label>Logo institusi<input type="file" name="logo" accept="image/png,image/jpeg"><small>PNG/JPG. Logo saat ini tetap digunakan apabila kosong.</small></label><div class="section-label">Tampilan</div><label>Warna utama<input type="color" name="primary_color" value="' . e(setting('primary_color', '#08784f')) . '"></label><label>Warna aksen<input type="color" name="secondary_color" value="' . e(setting('secondary_color', '#d5a328')) . '"></label><div class="section-label">Bobot Nilai Bawaan</div><label>Kehadiran (%)<input type="number" name="attendance_weight" min="0" max="100" value="' . e(setting('attendance_weight', '20')) . '"></label><label>Tugas/UTS (%)<input type="number" name="assignment_weight" min="0" max="100" value="' . e(setting('assignment_weight', '35')) . '"></label><label>UAS/Proyek (%)<input type="number" name="exam_weight" min="0" max="100" value="' . e(setting('exam_weight', '45')) . '"></label><label>Domain SIAKAD yang diizinkan<input name="api_allowed_origin" placeholder="https://asc.siakad.stai-aljawami.ac.id" value="' . e(setting('api_allowed_origin')) . '"><small>Digunakan untuk CORS API. Kosongkan jika integrasi hanya server-ke-server.</small></label><button class="btn span-2">Simpan Pengaturan</button></form></div></article>';
    render_footer();
}
