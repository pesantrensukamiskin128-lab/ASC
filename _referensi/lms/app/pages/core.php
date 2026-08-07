<?php
declare(strict_types=1);

function page_dashboard(array $user): void
{
    $role = $user['role'];
    if ($role === 'mahasiswa') {
        $stmt = db()->prepare('SELECT COUNT(*) FROM enrollments WHERE student_id=? AND status="active"');
        $stmt->execute([$user['id']]);
        $classCount = (int) $stmt->fetchColumn();
        $stmt = db()->prepare('SELECT COUNT(*) FROM assignments a JOIN enrollments e ON e.class_id=a.class_id LEFT JOIN submissions s ON s.assignment_id=a.id AND s.student_id=e.student_id WHERE e.student_id=? AND e.status="active" AND a.is_published=1 AND s.id IS NULL AND (a.due_at IS NULL OR a.due_at>=NOW())');
        $stmt->execute([$user['id']]);
        $pending = (int) $stmt->fetchColumn();
        $stmt = db()->prepare('SELECT COUNT(*) total,SUM(status IN ("hadir","terlambat")) present FROM attendance WHERE student_id=?');
        $stmt->execute([$user['id']]);
        $att = $stmt->fetch();
        $attendance = (int) $att['total'] ? round(((int) $att['present'] / (int) $att['total']) * 100) : 0;
        $stmt = db()->prepare('SELECT AVG((s.score/a.max_points)*100) FROM submissions s JOIN assignments a ON a.id=s.assignment_id WHERE s.student_id=? AND s.score IS NOT NULL');
        $stmt->execute([$user['id']]);
        $average = round((float) ($stmt->fetchColumn() ?: 0), 1);
        $stats = [
            stat_card('Mata Kuliah Aktif', $classCount, 'Semester berjalan'),
            stat_card('Tugas Belum Selesai', $pending, 'Perlu ditindaklanjuti', 'gold'),
            stat_card('Kehadiran', $attendance . '%', 'Akumulasi pertemuan', 'blue'),
            stat_card('Rata-rata Nilai', $average ?: '—', $average ? grade_letter($average) : 'Belum tersedia', 'purple'),
        ];
    } elseif ($role === 'dosen') {
        $stmt = db()->prepare('SELECT COUNT(*) FROM classes WHERE lecturer_id=? AND status="active"');
        $stmt->execute([$user['id']]);
        $classCount = (int) $stmt->fetchColumn();
        $stmt = db()->prepare('SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN classes c ON c.id=a.class_id WHERE c.lecturer_id=? AND s.score IS NULL');
        $stmt->execute([$user['id']]);
        $ungraded = (int) $stmt->fetchColumn();
        $stmt = db()->prepare('SELECT COUNT(*) FROM enrollments e JOIN classes c ON c.id=e.class_id WHERE c.lecturer_id=? AND e.status="active"');
        $stmt->execute([$user['id']]);
        $students = (int) $stmt->fetchColumn();
        $stmt = db()->prepare('SELECT COUNT(*) FROM class_meetings m JOIN classes c ON c.id=m.class_id WHERE c.lecturer_id=? AND m.journal IS NOT NULL AND m.journal<>""');
        $stmt->execute([$user['id']]);
        $journals = (int) $stmt->fetchColumn();
        $stats = [
            stat_card('Kelas Diampu', $classCount, 'Semester berjalan'),
            stat_card('Tugas Perlu Dinilai', $ungraded, 'Kiriman mahasiswa', 'gold'),
            stat_card('Mahasiswa Aktif', $students, 'Seluruh kelas', 'blue'),
            stat_card('Jurnal Terisi', $journals, 'Pertemuan terdokumentasi', 'purple'),
        ];
    } else {
        $whereProgram = $role === 'kaprodi' ? ' WHERE program_id=' . (int) $user['program_id'] : '';
        $classCount = (int) db()->query('SELECT COUNT(*) FROM classes' . $whereProgram . ($whereProgram ? ' AND' : ' WHERE') . ' status="active"')->fetchColumn();
        $userCount = (int) db()->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn();
        $courseCount = (int) db()->query('SELECT COUNT(*) FROM courses' . $whereProgram)->fetchColumn();
        $rpsCount = (int) db()->query('SELECT COUNT(*) FROM classes' . $whereProgram . ($whereProgram ? ' AND' : ' WHERE') . ' syllabus_status="approved"')->fetchColumn();
        $rpsPercent = $classCount ? round($rpsCount / $classCount * 100) : 0;
        $stats = [
            stat_card('Kelas Aktif', $classCount, 'Semester berjalan'),
            stat_card('Pengguna Aktif', $userCount, 'Dosen dan mahasiswa', 'blue'),
            stat_card('Mata Kuliah', $courseCount, 'Tersedia dalam kurikulum', 'purple'),
            stat_card('RPS Disetujui', $rpsPercent . '%', $rpsCount . ' dari ' . $classCount . ' kelas', 'gold'),
        ];
    }

    $classes = fetch_user_classes($user, 6);
    $annSql = 'SELECT a.*,u.full_name FROM announcements a LEFT JOIN users u ON u.id=a.created_by WHERE (a.audience_role IS NULL OR a.audience_role=? OR a.audience_role="all") AND (a.expires_at IS NULL OR a.expires_at>=NOW()) ORDER BY a.is_pinned DESC,a.published_at DESC LIMIT 5';
    $stmt = db()->prepare($annSql);
    $stmt->execute([$user['role']]);
    $announcements = $stmt->fetchAll();
    $due = [];
    if ($role === 'mahasiswa') {
        $stmt = db()->prepare('SELECT a.*,co.name course_name,c.name class_name,s.id submission_id FROM assignments a JOIN classes c ON c.id=a.class_id JOIN courses co ON co.id=c.course_id JOIN enrollments e ON e.class_id=c.id LEFT JOIN submissions s ON s.assignment_id=a.id AND s.student_id=? WHERE e.student_id=? AND a.is_published=1 AND a.due_at>=NOW() ORDER BY a.due_at LIMIT 6');
        $stmt->execute([$user['id'], $user['id']]);
        $due = $stmt->fetchAll();
    } elseif ($role === 'dosen') {
        $stmt = db()->prepare('SELECT a.*,co.name course_name,c.name class_name,(SELECT COUNT(*) FROM submissions s WHERE s.assignment_id=a.id AND s.score IS NULL) pending_count FROM assignments a JOIN classes c ON c.id=a.class_id JOIN courses co ON co.id=c.course_id WHERE c.lecturer_id=? ORDER BY a.due_at DESC LIMIT 6');
        $stmt->execute([$user['id']]);
        $due = $stmt->fetchAll();
    }

    render_header('Beranda');
    page_heading('Selamat datang, ' . explode(' ', $user['full_name'])[0], 'Ringkasan pembelajaran dan aktivitas akademik Anda hari ini.');
    echo '<section class="stats-grid">' . implode('', $stats) . '</section>';
    echo '<section class="grid-2"><div>';
    echo '<article class="panel"><div class="panel-head"><div><h2>' . ($role === 'mahasiswa' ? 'Mata Kuliah Saya' : 'Kelas Pembelajaran') . '</h2><p>Akses cepat kelas semester berjalan</p></div><a class="btn btn-secondary btn-small" href="index.php?page=my-classes">Lihat semua</a></div><div class="panel-body">';
    if (!$classes) {
        empty_state('Belum ada kelas', 'Kelas aktif akan tampil setelah data akademik ditambahkan.');
    } else {
        echo '<div class="course-grid">';
        foreach ($classes as $class) {
            render_course_card($class);
        }
        echo '</div>';
    }
    echo '</div></article>';
    if ($due) {
        echo '<article class="panel"><div class="panel-head"><div><h2>' . ($role === 'dosen' ? 'Aktivitas Penilaian' : 'Tenggat Terdekat') . '</h2><p>Prioritas yang perlu ditindaklanjuti</p></div></div><div class="quick-list panel-body">';
        foreach ($due as $item) {
            $note = $role === 'dosen' ? ((int) ($item['pending_count'] ?? 0) . ' belum dinilai') : (($item['submission_id'] ?? null) ? 'Sudah dikumpulkan' : 'Belum dikumpulkan');
            echo '<a class="quick-item" href="index.php?page=class&id=' . (int) $item['class_id'] . '&tab=assignments"><span class="quick-icon">□</span><span><b>' . e($item['title']) . '</b><small>' . e($item['course_name']) . ' · ' . e($note) . '</small></span><small style="margin-left:auto">' . ($item['due_at'] ? e(date('d M', strtotime($item['due_at']))) : '—') . '</small></a>';
        }
        echo '</div></article>';
    }
    echo '</div><aside>';
    echo '<article class="panel"><div class="panel-head"><div><h2>Pengumuman</h2><p>Informasi terbaru kampus dan kelas</p></div></div><div class="panel-body quick-list">';
    if (!$announcements) {
        empty_state('Belum ada pengumuman', 'Informasi baru akan muncul di sini.');
    } else {
        foreach ($announcements as $announcement) {
            echo '<div class="quick-item"><span class="quick-icon">◉</span><span><b>' . e($announcement['title']) . '</b><small>' . e(mb_strimwidth(strip_tags($announcement['body']), 0, 80, '…')) . '</small></span></div>';
        }
    }
    echo '</div></article>';
    echo '<article class="panel"><div class="panel-head"><h2>Status Sistem</h2></div><div class="panel-body metric-list"><div class="metric-row"><span>Semester aktif</span><b>' . e(active_academic_year()) . '</b></div><div class="metric-row"><span>Akses akun</span><b class="ok">Terverifikasi</b></div><div class="metric-row"><span>Mode pembelajaran</span><b>Hybrid</b></div></div></article>';
    echo '</aside></section>';
    render_footer();
}

function fetch_user_classes(array $user, ?int $limit = null): array
{
    $params = [];
    $where = 'c.status="active"';
    if ($user['role'] === 'mahasiswa') {
        $join = ' JOIN enrollments e ON e.class_id=c.id ';
        $where .= ' AND e.student_id=? AND e.status="active"';
        $params[] = $user['id'];
    } elseif ($user['role'] === 'dosen') {
        $join = '';
        $where .= ' AND c.lecturer_id=?';
        $params[] = $user['id'];
    } elseif ($user['role'] === 'kaprodi') {
        $join = '';
        $where .= ' AND c.program_id=?';
        $params[] = $user['program_id'];
    } else {
        $join = '';
    }
    $sql = 'SELECT c.*,co.code course_code,co.name course_name,co.credits,p.name program_name,p.color,u.full_name lecturer_name,ay.name academic_year_name,(SELECT COUNT(*) FROM enrollments en WHERE en.class_id=c.id AND en.status="active") student_count,(SELECT COUNT(*) FROM class_meetings cm WHERE cm.class_id=c.id) meeting_count FROM classes c ' . $join . ' JOIN courses co ON co.id=c.course_id LEFT JOIN programs p ON p.id=c.program_id LEFT JOIN users u ON u.id=c.lecturer_id LEFT JOIN academic_years ay ON ay.id=c.academic_year_id WHERE ' . $where . ' ORDER BY co.name,c.name' . ($limit ? ' LIMIT ' . $limit : '');
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function render_course_card(array $class): void
{
    $progress = min(100, round(((int) $class['meeting_count'] / 16) * 100));
    echo '<a class="course-card" href="index.php?page=class&id=' . (int) $class['id'] . '" style="--course-color:' . e($class['color'] ?: '#08784f') . '"><div class="course-accent"></div><div class="course-body"><div class="course-code">' . e($class['course_code']) . ' · ' . e($class['credits']) . ' SKS</div><h3>' . e($class['course_name']) . '</h3><div class="course-meta"><span>Kelas ' . e($class['name']) . '</span><span>' . e($class['lecturer_name'] ?: 'Belum ada dosen') . '</span><span>' . e($class['student_count']) . ' mahasiswa</span></div></div><div class="course-footer"><div class="progress"><i style="width:' . $progress . '%"></i></div><small>' . e($class['meeting_count']) . '/16 pertemuan</small></div></a>';
}

function active_academic_year(): string
{
    $row = db()->query('SELECT name,semester FROM academic_years WHERE is_active=1 ORDER BY id DESC LIMIT 1')->fetch();
    return $row ? $row['name'] . ' · ' . ucfirst($row['semester']) : 'Belum ditetapkan';
}

function page_my_classes(array $user): void
{
    $classes = fetch_user_classes($user);
    render_header('Kelas Pembelajaran');
    page_heading($user['role'] === 'mahasiswa' ? 'Mata Kuliah Saya' : 'Kelas Pembelajaran', 'Seluruh ruang belajar yang dapat Anda akses pada semester berjalan.');
    if (!$classes) {
        echo '<article class="panel">';
        empty_state('Belum ada kelas aktif', 'Hubungi administrator akademik apabila seharusnya Anda sudah terdaftar dalam kelas.');
        echo '</article>';
    } else {
        echo '<div class="course-grid">';
        foreach ($classes as $class) {
            render_course_card($class);
        }
        echo '</div>';
    }
    render_footer();
}

function page_classroom(array $user): void
{
    $classId = (int) ($_GET['id'] ?? 0);
    $class = class_access($classId, $user);
    if (!$class) {
        http_response_code(403);
        render_error_page('Kelas tidak dapat diakses', 'Kelas tidak ditemukan atau Anda belum terdaftar.');
        return;
    }
    $canManage = in_array($user['role'], ['super_admin', 'admin'], true) || ($user['role'] === 'dosen' && (int) $class['lecturer_id'] === (int) $user['id']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        handle_class_action($class, $user, $canManage);
        redirect('index.php?page=class&id=' . $classId . '&tab=' . urlencode((string) ($_POST['return_tab'] ?? 'stream')));
    }

    $stmt = db()->prepare('SELECT * FROM class_meetings WHERE class_id=? ORDER BY meeting_number');
    $stmt->execute([$classId]);
    $meetings = $stmt->fetchAll();
    $stmt = db()->prepare('SELECT m.*,cm.meeting_number FROM materials m LEFT JOIN class_meetings cm ON cm.id=m.meeting_id WHERE m.class_id=? ORDER BY COALESCE(cm.meeting_number,99),m.created_at DESC');
    $stmt->execute([$classId]);
    $materials = $stmt->fetchAll();
    $stmt = db()->prepare('SELECT a.*,o.code outcome_code,(SELECT COUNT(*) FROM submissions s WHERE s.assignment_id=a.id) submission_count FROM assignments a LEFT JOIN course_outcomes o ON o.id=a.outcome_id WHERE a.class_id=? ORDER BY a.due_at,a.created_at');
    $stmt->execute([$classId]);
    $assignments = $stmt->fetchAll();
    $stmt = db()->prepare('SELECT u.id,u.full_name,u.identity_number,u.email,e.status FROM enrollments e JOIN users u ON u.id=e.student_id WHERE e.class_id=? ORDER BY u.full_name');
    $stmt->execute([$classId]);
    $roster = $stmt->fetchAll();
    $stmt = db()->prepare('SELECT * FROM course_outcomes WHERE course_id=? ORDER BY code');
    $stmt->execute([$class['course_id']]);
    $outcomes = $stmt->fetchAll();
    $tab = (string) ($_GET['tab'] ?? 'stream');

    render_header($class['course_name']);
    $action = $canManage ? '<button class="btn" data-modal="meeting-modal">+ Pertemuan</button>' : '';
    page_heading($class['course_name'], $class['course_code'] . ' · Kelas ' . $class['name'] . ' · ' . ($class['lecturer_name'] ?: 'Belum ada dosen'), $action);
    echo '<div class="panel"><div class="panel-body"><div class="course-meta"><span>' . status_badge($class['mode']) . '</span><span>◷ ' . e(($class['schedule_day'] ?: 'Jadwal belum diatur') . ' ' . ($class['schedule_time'] ?: '')) . '</span><span>⌂ ' . e($class['room'] ?: 'Ruang virtual') . '</span><span>♙ ' . count($roster) . ' mahasiswa</span></div></div></div>';
    echo '<div class="tabs" data-tabs="class-tabs">';
    $tabs = ['stream' => 'Ringkasan', 'materials' => 'Materi', 'meetings' => 'Pertemuan & Presensi', 'assignments' => 'Tugas & Ujian', 'discussions' => 'Diskusi', 'grades' => 'Buku Nilai', 'outcomes' => 'CPMK'];
    foreach ($tabs as $key => $label) {
        echo '<a class="tab ' . ($tab === $key ? 'active' : '') . '" href="index.php?page=class&id=' . $classId . '&tab=' . $key . '">' . e($label) . '</a>';
    }
    echo '</div>';

    if ($tab === 'materials') {
        render_materials_tab($class, $materials, $meetings, $canManage);
    } elseif ($tab === 'meetings') {
        render_meetings_tab($class, $meetings, $roster, $user, $canManage);
    } elseif ($tab === 'assignments') {
        render_assignments_tab($class, $assignments, $outcomes, $user, $canManage);
    } elseif ($tab === 'discussions') {
        render_discussions_tab($class, $user, $canManage);
    } elseif ($tab === 'grades') {
        render_gradebook_tab($class, $assignments, $roster, $user, $canManage);
    } elseif ($tab === 'outcomes') {
        render_outcomes_tab($class, $outcomes, $canManage);
    } else {
        render_class_stream($class, $meetings, $materials, $assignments, $user);
    }
    if ($canManage) {
        render_class_modals($class, $meetings, $outcomes);
    }
    render_footer();
}

function handle_class_action(array $class, array $user, bool $canManage): void
{
    $action = (string) ($_POST['action'] ?? '');
    $classId = (int) $class['id'];
    if ($action === 'add_meeting' && $canManage) {
        $token = strtoupper(substr(bin2hex(random_bytes(5)), 0, 6));
        $stmt = db()->prepare('INSERT INTO class_meetings (class_id,meeting_number,title,description,meeting_date,starts_at,ends_at,mode,attendance_token,attendance_opens_at,attendance_closes_at,journal,is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)');
        $date = (string) $_POST['meeting_date'];
        $starts = $_POST['starts_at'] ?: null;
        $ends = $_POST['ends_at'] ?: null;
        $stmt->execute([$classId, (int) $_POST['meeting_number'], trim((string) $_POST['title']), trim((string) ($_POST['description'] ?? '')), $date, $starts, $ends, $_POST['mode'], $token, $date . ' ' . ($starts ?: '00:00:00'), $date . ' ' . ($ends ?: '23:59:59'), trim((string) ($_POST['journal'] ?? ''))]);
        audit('create', 'class_meetings', (int) db()->lastInsertId(), 'Menambah pertemuan kelas ' . $classId);
        flash('success', 'Pertemuan berhasil ditambahkan. Token presensi: ' . $token);
    } elseif ($action === 'upload_syllabus' && $canManage) {
        $upload = upload_file('syllabus_file', 'syllabus');
        if (!$upload || $upload['mime_type'] !== 'application/pdf') {
            throw new RuntimeException('RPS wajib diunggah dalam format PDF.');
        }
        db()->prepare('UPDATE classes SET syllabus_path=?,syllabus_status="submitted",syllabus_note=NULL,updated_at=NOW() WHERE id=?')->execute([$upload['stored_path'], $classId]);
        audit('submit', 'syllabus', $classId, 'Mengunggah RPS untuk peninjauan');
        flash('success', 'RPS berhasil diunggah dan diajukan untuk peninjauan.');
    } elseif ($action === 'review_syllabus' && in_array($user['role'], ['super_admin', 'admin', 'kaprodi'], true)) {
        $status = in_array($_POST['syllabus_status'] ?? '', ['approved', 'revision'], true) ? $_POST['syllabus_status'] : 'revision';
        db()->prepare('UPDATE classes SET syllabus_status=?,syllabus_note=?,updated_at=NOW() WHERE id=?')->execute([$status, trim((string) ($_POST['syllabus_note'] ?? '')), $classId]);
        audit('review', 'syllabus', $classId, 'Status RPS: ' . $status);
        flash('success', 'Hasil peninjauan RPS berhasil disimpan.');
    } elseif ($action === 'add_material' && $canManage) {
        $upload = upload_file('material_file', 'materials');
        $type = $upload ? 'file' : (string) ($_POST['material_type'] ?? 'link');
        $stmt = db()->prepare('INSERT INTO materials (class_id,meeting_id,title,description,material_type,content,file_path,original_name,mime_type,file_size,is_published,available_at,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,1,?,?)');
        $stmt->execute([$classId, (int) ($_POST['meeting_id'] ?? 0) ?: null, trim((string) $_POST['title']), trim((string) ($_POST['description'] ?? '')), $type, trim((string) ($_POST['content'] ?? '')), $upload['stored_path'] ?? null, $upload['original_name'] ?? null, $upload['mime_type'] ?? null, $upload['size_bytes'] ?? null, $_POST['available_at'] ?: null, $user['id']]);
        audit('create', 'materials', (int) db()->lastInsertId(), 'Menambah materi kelas ' . $classId);
        flash('success', 'Materi pembelajaran berhasil diterbitkan.');
    } elseif ($action === 'add_assignment' && $canManage) {
        $stmt = db()->prepare('INSERT INTO assignments (class_id,outcome_id,title,instructions,assignment_type,opens_at,due_at,max_points,weight,allow_late,max_attempts,is_published,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?)');
        $stmt->execute([$classId, (int) ($_POST['outcome_id'] ?? 0) ?: null, trim((string) $_POST['title']), trim((string) ($_POST['instructions'] ?? '')), $_POST['assignment_type'], $_POST['opens_at'] ?: null, $_POST['due_at'] ?: null, (float) $_POST['max_points'], (float) $_POST['weight'], isset($_POST['allow_late']) ? 1 : 0, max(1, (int) $_POST['max_attempts']), $user['id']]);
        $assignmentId = (int) db()->lastInsertId();
        audit('create', 'assignments', $assignmentId, 'Menambah tugas kelas ' . $classId);
        notify_class($classId, 'Tugas baru: ' . trim((string) $_POST['title']), 'Periksa petunjuk dan tenggat pengumpulan pada kelas ' . $class['course_name'] . '.', 'index.php?page=class&id=' . $classId . '&tab=assignments');
        flash('success', 'Tugas atau ujian berhasil dibuat.');
    } elseif ($action === 'submit_assignment' && $user['role'] === 'mahasiswa') {
        $assignmentId = (int) $_POST['assignment_id'];
        $stmt = db()->prepare('SELECT * FROM assignments WHERE id=? AND class_id=? AND is_published=1');
        $stmt->execute([$assignmentId, $classId]);
        $assignment = $stmt->fetch();
        if (!$assignment) {
            throw new RuntimeException('Tugas tidak ditemukan.');
        }
        if ($assignment['due_at'] && strtotime($assignment['due_at']) < time() && !$assignment['allow_late']) {
            throw new RuntimeException('Waktu pengumpulan tugas telah berakhir.');
        }
        $upload = upload_file('submission_file', 'submissions');
        $text = trim((string) ($_POST['submission_text'] ?? ''));
        if (!$upload && $text === '') {
            throw new RuntimeException('Tambahkan jawaban atau berkas tugas.');
        }
        $late = $assignment['due_at'] && strtotime($assignment['due_at']) < time() ? 1 : 0;
        $stmt = db()->prepare('INSERT INTO submissions (assignment_id,student_id,submission_text,file_path,original_name,mime_type,file_size,submitted_at,is_late,status) VALUES (?,?,?,?,?,?,?,NOW(),?,"submitted") ON DUPLICATE KEY UPDATE submission_text=VALUES(submission_text),file_path=COALESCE(VALUES(file_path),file_path),original_name=COALESCE(VALUES(original_name),original_name),mime_type=COALESCE(VALUES(mime_type),mime_type),file_size=COALESCE(VALUES(file_size),file_size),submitted_at=NOW(),is_late=VALUES(is_late),score=NULL,feedback=NULL,status="submitted"');
        $stmt->execute([$assignmentId, $user['id'], $text, $upload['stored_path'] ?? null, $upload['original_name'] ?? null, $upload['mime_type'] ?? null, $upload['size_bytes'] ?? null, $late]);
        audit('submit', 'assignments', $assignmentId, 'Mahasiswa mengumpulkan tugas');
        flash('success', 'Tugas berhasil dikumpulkan' . ($late ? ' dan tercatat terlambat.' : '.'));
    } elseif ($action === 'grade_submission' && $canManage) {
        $submissionId = (int) $_POST['submission_id'];
        $owner = db()->prepare('SELECT student_id FROM submissions WHERE id=?');
        $owner->execute([$submissionId]);
        $studentId = (int) $owner->fetchColumn();
        $stmt = db()->prepare('UPDATE submissions s JOIN assignments a ON a.id=s.assignment_id SET s.score=?,s.feedback=?,s.graded_by=?,s.graded_at=NOW(),s.status="graded" WHERE s.id=? AND a.class_id=?');
        $stmt->execute([(float) $_POST['score'], trim((string) ($_POST['feedback'] ?? '')), $user['id'], $submissionId, $classId]);
        audit('grade', 'submissions', $submissionId, 'Menilai kiriman mahasiswa');
        if ($studentId) notify_user($studentId, 'Tugas telah dinilai', 'Nilai dan umpan balik baru tersedia pada ' . $class['course_name'] . '.', 'index.php?page=class&id=' . $classId . '&tab=assignments');
        flash('success', 'Nilai dan umpan balik berhasil disimpan.');
    } elseif ($action === 'save_attendance' && $canManage) {
        $meetingId = (int) $_POST['meeting_id'];
        $check = db()->prepare('SELECT 1 FROM class_meetings WHERE id=? AND class_id=?');
        $check->execute([$meetingId, $classId]);
        if (!$check->fetchColumn()) {
            throw new RuntimeException('Pertemuan tidak valid.');
        }
        $stmt = db()->prepare('INSERT INTO attendance (meeting_id,student_id,status,attendance_mode,note,checked_in_at,recorded_by) VALUES (?,?,?,?,?,NOW(),?) ON DUPLICATE KEY UPDATE status=VALUES(status),attendance_mode=VALUES(attendance_mode),note=VALUES(note),checked_in_at=NOW(),recorded_by=VALUES(recorded_by)');
        foreach ($_POST['attendance'] ?? [] as $studentId => $status) {
            $stmt->execute([$meetingId, (int) $studentId, $status, $_POST['attendance_mode'][$studentId] ?? null, $_POST['attendance_note'][$studentId] ?? null, $user['id']]);
        }
        audit('attendance', 'class_meetings', $meetingId, 'Memperbarui presensi kelas');
        flash('success', 'Presensi mahasiswa berhasil diperbarui.');
    } elseif ($action === 'student_checkin' && $user['role'] === 'mahasiswa') {
        $token = strtoupper(trim((string) ($_POST['token'] ?? '')));
        $stmt = db()->prepare('SELECT * FROM class_meetings WHERE class_id=? AND attendance_token=? AND NOW() BETWEEN attendance_opens_at AND attendance_closes_at LIMIT 1');
        $stmt->execute([$classId, $token]);
        $meeting = $stmt->fetch();
        if (!$meeting) {
            throw new RuntimeException('Token tidak sesuai atau waktu presensi telah berakhir.');
        }
        $stmt = db()->prepare('INSERT INTO attendance (meeting_id,student_id,status,attendance_mode,checked_in_at,recorded_by) VALUES (?,? ,"hadir",?,NOW(),?) ON DUPLICATE KEY UPDATE status="hadir",attendance_mode=VALUES(attendance_mode),checked_in_at=NOW()');
        $stmt->execute([$meeting['id'], $user['id'], $_POST['attendance_mode'] ?? 'online', $user['id']]);
        audit('checkin', 'class_meetings', (int) $meeting['id'], 'Presensi mandiri mahasiswa');
        flash('success', 'Kehadiran Anda berhasil dicatat.');
    } elseif ($action === 'add_outcome' && $canManage) {
        $stmt = db()->prepare('INSERT INTO course_outcomes (course_id,code,description,weight) VALUES (?,?,?,?)');
        $stmt->execute([$class['course_id'], trim((string) $_POST['code']), trim((string) $_POST['description']), (float) $_POST['weight']]);
        audit('create', 'course_outcomes', (int) db()->lastInsertId(), 'Menambah CPMK');
        flash('success', 'CPMK berhasil ditambahkan.');
    } elseif ($action === 'new_discussion') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($title === '' || $body === '') throw new RuntimeException('Judul dan isi diskusi wajib diisi.');
        db()->prepare('INSERT INTO discussion_threads (class_id,title,body,is_pinned,created_by,created_at) VALUES (?,?,?,?,?,NOW())')->execute([$classId, $title, $body, ($canManage && isset($_POST['is_pinned'])) ? 1 : 0, $user['id']]);
        audit('create', 'discussion_threads', (int) db()->lastInsertId(), 'Membuka diskusi kelas');
        flash('success', 'Topik diskusi berhasil diterbitkan.');
    } elseif ($action === 'add_discussion_post') {
        $threadId = (int) ($_POST['thread_id'] ?? 0);
        $body = trim((string) ($_POST['body'] ?? ''));
        $check = db()->prepare('SELECT 1 FROM discussion_threads WHERE id=? AND class_id=? AND is_locked=0');
        $check->execute([$threadId, $classId]);
        if (!$check->fetchColumn() || $body === '') throw new RuntimeException('Topik terkunci atau tanggapan kosong.');
        db()->prepare('INSERT INTO discussion_posts (thread_id,body,created_by,created_at) VALUES (?,?,?,NOW())')->execute([$threadId, $body, $user['id']]);
        audit('create', 'discussion_posts', (int) db()->lastInsertId(), 'Menanggapi diskusi kelas');
        flash('success', 'Tanggapan berhasil dikirim.');
    } else {
        throw new RuntimeException('Tindakan tidak dikenali atau tidak diizinkan.');
    }
}

function render_class_stream(array $class, array $meetings, array $materials, array $assignments, array $user): void
{
    echo '<section class="grid-2"><div>';
    echo '<article class="panel"><div class="panel-head"><div><h2>Alur Pembelajaran</h2><p>Aktivitas kelas berdasarkan pertemuan</p></div></div><div class="panel-body timeline">';
    if (!$meetings) {
        empty_state('Belum ada pertemuan', 'Dosen belum menerbitkan agenda pertemuan.');
    } else {
        foreach (array_slice(array_reverse($meetings), 0, 8) as $meeting) {
            echo '<div class="timeline-item"><div class="timeline-number">' . (int) $meeting['meeting_number'] . '</div><div class="timeline-content"><h4>' . e($meeting['title']) . '</h4><p>' . e(date('d M Y', strtotime($meeting['meeting_date']))) . ' · ' . status_badge($meeting['mode']) . '</p><p>' . e(mb_strimwidth((string) ($meeting['description'] ?: $meeting['journal']), 0, 150, '…')) . '</p></div></div>';
        }
    }
    echo '</div></article></div><aside>';
    echo '<article class="panel"><div class="panel-head"><h2>Informasi Kelas</h2></div><div class="panel-body metric-list">';
    $items = [
        'Program Studi' => $class['program_name'] ?: 'Lintas program studi',
        'Tahun Akademik' => $class['academic_year_name'] ?: '—',
        'Moda' => ucfirst($class['mode']),
        'Ruangan' => $class['room'] ?: 'Ruang virtual',
        'RPS' => ucfirst($class['syllabus_status']),
    ];
    foreach ($items as $key => $value) {
        echo '<div class="metric-row"><span>' . e($key) . '</span><b>' . e($value) . '</b></div>';
    }
    if ($class['meeting_url']) {
        echo '<a class="btn btn-block" href="' . e($class['meeting_url']) . '" target="_blank" rel="noopener">Buka Kelas Online</a>';
    }
    echo '</div></article>';
    echo '<article class="panel"><div class="panel-head"><div><h2>Rencana Pembelajaran Semester</h2><p>Status: ' . e(ucfirst($class['syllabus_status'])) . '</p></div>' . status_badge($class['syllabus_status']) . '</div><div class="panel-body">';
    if ($class['syllabus_path']) {
        echo '<p><a class="btn btn-secondary btn-small" href="index.php?page=file&source=syllabus&id=' . (int) $class['id'] . '" target="_blank">Buka RPS</a></p>';
    }
    if ($class['syllabus_note']) {
        echo '<div class="alert alert-info"><span><b>Catatan peninjau:</b> ' . e($class['syllabus_note']) . '</span></div>';
    }
    $canUploadRps = in_array($user['role'], ['super_admin', 'admin'], true) || ($user['role'] === 'dosen' && (int) $class['lecturer_id'] === (int) $user['id']);
    if ($canUploadRps) {
        echo '<form method="post" enctype="multipart/form-data" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="upload_syllabus"><input type="hidden" name="return_tab" value="stream"><label class="span-2">Unggah RPS PDF<input type="file" name="syllabus_file" accept="application/pdf" required></label><button class="btn span-2">' . ($class['syllabus_path'] ? 'Unggah Revisi RPS' : 'Ajukan RPS') . '</button></form>';
    }
    if (in_array($user['role'], ['super_admin', 'admin', 'kaprodi'], true) && $class['syllabus_path']) {
        echo '<form method="post" class="form-grid" style="margin-top:15px">' . csrf_field() . '<input type="hidden" name="action" value="review_syllabus"><input type="hidden" name="return_tab" value="stream"><label>Keputusan<select name="syllabus_status"><option value="approved">Setujui</option><option value="revision">Minta Revisi</option></select></label><label>Catatan<textarea name="syllabus_note">' . e($class['syllabus_note']) . '</textarea></label><button class="btn span-2">Simpan Hasil Peninjauan</button></form>';
    }
    echo '</div></article>';
    echo '<article class="panel"><div class="panel-head"><h2>Ringkasan Konten</h2></div><div class="panel-body stats-grid" style="grid-template-columns:repeat(3,1fr);margin:0">' . stat_card('Materi', count($materials), 'Sumber belajar') . stat_card('Tugas', count($assignments), 'Asesmen') . stat_card('Pertemuan', count($meetings), 'Dari 16') . '</div></article>';
    if ($user['role'] === 'mahasiswa') {
        echo '<article class="panel"><div class="panel-head"><h2>Presensi Mandiri</h2></div><div class="panel-body"><form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="student_checkin"><input type="hidden" name="return_tab" value="stream"><label class="span-2">Token presensi<input name="token" maxlength="20" required placeholder="Masukkan token dari dosen"></label><label class="span-2">Mengikuti melalui<select name="attendance_mode"><option value="offline">Tatap muka</option><option value="online">Online</option></select></label><button class="btn span-2" type="submit">Catat Kehadiran</button></form></div></article>';
    }
    echo '</aside></section>';
}

function render_materials_tab(array $class, array $materials, array $meetings, bool $canManage): void
{
    $materialModals = '';
    $action = $canManage ? '<button class="btn" data-modal="material-modal">+ Tambah Materi</button>' : '';
    echo '<article class="panel"><div class="panel-head"><div><h2>Materi Pembelajaran</h2><p>Dokumen, video, tautan, dan sumber belajar kelas</p></div>' . $action . '</div>';
    if (!$materials) {
        empty_state('Materi belum tersedia', 'Materi pembelajaran yang diterbitkan dosen akan muncul di sini.');
    } else {
        echo '<div class="table-wrap"><table class="data-table"><thead><tr><th>Pertemuan</th><th>Materi</th><th>Jenis</th><th>Tersedia</th><th>Akses</th></tr></thead><tbody>';
        foreach ($materials as $material) {
            $link = '';
            if ($material['material_type'] === 'file' && $material['file_path']) {
                $link = '<a class="btn btn-secondary btn-small" href="index.php?page=file&source=material&id=' . (int) $material['id'] . '" target="_blank">Buka berkas</a>';
            } elseif (in_array($material['material_type'], ['link', 'video'], true) && $material['content']) {
                $link = '<a class="btn btn-secondary btn-small" href="' . e($material['content']) . '" target="_blank" rel="noopener">Buka tautan</a>';
            } elseif ($material['content']) {
                $link = '<button class="btn btn-secondary btn-small" data-modal="material-' . (int) $material['id'] . '">Baca</button>';
            }
            echo '<tr><td>' . ($material['meeting_number'] ? 'Ke-' . (int) $material['meeting_number'] : 'Umum') . '</td><td><div class="table-title">' . e($material['title']) . '</div><div class="table-subtitle">' . e($material['description']) . '</div></td><td>' . e(ucfirst($material['material_type'])) . '</td><td>' . e($material['available_at'] ? date('d M Y, H:i', strtotime($material['available_at'])) : 'Sekarang') . '</td><td>' . $link . '</td></tr>';
            if ($material['material_type'] === 'text' && $material['content']) {
                ob_start();
                modal_start('material-' . (int) $material['id'], $material['title']);
                echo '<div style="white-space:pre-wrap;line-height:1.7">' . e($material['content']) . '</div>';
                modal_end();
                $materialModals .= (string) ob_get_clean();
            }
        }
        echo '</tbody></table></div>';
    }
    echo '</article>';
    echo $materialModals;
}

function render_meetings_tab(array $class, array $meetings, array $roster, array $user, bool $canManage): void
{
    if (!$meetings) {
        echo '<article class="panel">';
        empty_state('Belum ada pertemuan', 'Tambahkan pertemuan untuk membuka jurnal dan presensi.');
        echo '</article>';
        return;
    }
    echo '<div class="grid-equal">';
    foreach ($meetings as $meeting) {
        $stmt = db()->prepare('SELECT status,COUNT(*) total FROM attendance WHERE meeting_id=? GROUP BY status');
        $stmt->execute([$meeting['id']]);
        $attendance = [];
        foreach ($stmt->fetchAll() as $row) {
            $attendance[$row['status']] = (int) $row['total'];
        }
        echo '<article class="panel"><div class="panel-head"><div><h2>Pertemuan ' . (int) $meeting['meeting_number'] . '</h2><p>' . e(date('d F Y', strtotime($meeting['meeting_date']))) . '</p></div>' . status_badge($meeting['mode']) . '</div><div class="panel-body"><h3 style="margin-top:0">' . e($meeting['title']) . '</h3><p style="color:var(--muted);font-size:13px">' . e($meeting['description']) . '</p><div class="course-meta"><span>Hadir ' . (int) ($attendance['hadir'] ?? 0) . '</span><span>Izin/Sakit ' . ((int) ($attendance['izin'] ?? 0) + (int) ($attendance['sakit'] ?? 0)) . '</span><span>Alpa ' . (int) ($attendance['alpa'] ?? 0) . '</span></div>';
        if ($canManage) {
            echo '<p><b>Token presensi:</b> <code>' . e($meeting['attendance_token']) . '</code></p><button class="btn btn-secondary btn-small" data-modal="attendance-' . (int) $meeting['id'] . '">Kelola Presensi</button>';
        } elseif ($user['role'] === 'mahasiswa') {
            $stmt = db()->prepare('SELECT status,checked_in_at FROM attendance WHERE meeting_id=? AND student_id=?');
            $stmt->execute([$meeting['id'], $user['id']]);
            $own = $stmt->fetch();
            echo '<p>Status Anda: ' . ($own ? status_badge($own['status']) : '<span class="badge">Belum tercatat</span>') . '</p>';
        }
        echo '</div></article>';

        if ($canManage) {
            modal_start('attendance-' . (int) $meeting['id'], 'Presensi Pertemuan ' . $meeting['meeting_number']);
            $stmt = db()->prepare('SELECT student_id,status,attendance_mode,note FROM attendance WHERE meeting_id=?');
            $stmt->execute([$meeting['id']]);
            $existing = [];
            foreach ($stmt->fetchAll() as $item) {
                $existing[$item['student_id']] = $item;
            }
            echo '<form method="post"><input type="hidden" name="action" value="save_attendance"><input type="hidden" name="return_tab" value="meetings"><input type="hidden" name="meeting_id" value="' . (int) $meeting['id'] . '">' . csrf_field() . '<div class="table-wrap"><table class="data-table"><thead><tr><th>Mahasiswa</th><th>Status</th><th>Moda</th><th>Catatan</th></tr></thead><tbody>';
            foreach ($roster as $student) {
                $current = $existing[$student['id']] ?? ['status' => 'alpa', 'attendance_mode' => 'offline', 'note' => ''];
                echo '<tr><td><b>' . e($student['full_name']) . '</b><small style="display:block">' . e($student['identity_number']) . '</small></td><td><select name="attendance[' . (int) $student['id'] . ']">';
                foreach (['hadir', 'terlambat', 'izin', 'sakit', 'alpa'] as $status) {
                    echo '<option value="' . $status . '" ' . ($current['status'] === $status ? 'selected' : '') . '>' . ucfirst($status) . '</option>';
                }
                echo '</select></td><td><select name="attendance_mode[' . (int) $student['id'] . ']"><option value="offline" ' . ($current['attendance_mode'] === 'offline' ? 'selected' : '') . '>Tatap muka</option><option value="online" ' . ($current['attendance_mode'] === 'online' ? 'selected' : '') . '>Online</option></select></td><td><input name="attendance_note[' . (int) $student['id'] . ']" value="' . e($current['note']) . '"></td></tr>';
            }
            echo '</tbody></table></div><div class="form-actions"><button class="btn" type="submit">Simpan Presensi</button></div></form>';
            modal_end();
        }
    }
    echo '</div>';
}

function render_assignments_tab(array $class, array $assignments, array $outcomes, array $user, bool $canManage): void
{
    $assignmentModals = '';
    $action = $canManage ? '<button class="btn" data-modal="assignment-modal">+ Buat Tugas/Ujian</button>' : '';
    echo '<article class="panel"><div class="panel-head"><div><h2>Tugas dan Asesmen</h2><p>Penilaian individu, proyek, kuis, UTS, dan UAS</p></div>' . $action . '</div>';
    if (!$assignments) {
        empty_state('Belum ada tugas', 'Tugas yang diterbitkan akan tampil pada halaman ini.');
    } else {
        echo '<div class="table-wrap"><table class="data-table"><thead><tr><th>Asesmen</th><th>Jenis</th><th>CPMK</th><th>Bobot</th><th>Tenggat</th><th>Status/Aksi</th></tr></thead><tbody>';
        foreach ($assignments as $assignment) {
            $status = '';
            if ($user['role'] === 'mahasiswa') {
                $stmt = db()->prepare('SELECT * FROM submissions WHERE assignment_id=? AND student_id=?');
                $stmt->execute([$assignment['id'], $user['id']]);
                $submission = $stmt->fetch();
                $status = $submission ? status_badge($submission['status']) . ' <button class="btn btn-secondary btn-small" data-modal="submit-' . (int) $assignment['id'] . '">Lihat/Perbarui</button>' : '<button class="btn btn-small" data-modal="submit-' . (int) $assignment['id'] . '">Kumpulkan</button>';
                ob_start();
                modal_start('submit-' . (int) $assignment['id'], $assignment['title']);
                echo '<p style="white-space:pre-wrap">' . e($assignment['instructions']) . '</p><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="action" value="submit_assignment"><input type="hidden" name="return_tab" value="assignments"><input type="hidden" name="assignment_id" value="' . (int) $assignment['id'] . '">' . csrf_field() . '<label class="span-2">Jawaban tertulis<textarea name="submission_text">' . e($submission['submission_text'] ?? '') . '</textarea></label><label class="span-2">Berkas jawaban<input type="file" name="submission_file"><small>PDF, Word, PowerPoint, Excel, gambar, audio, video, atau ZIP.</small></label>';
                if (!empty($submission['file_path'])) {
                    echo '<p class="span-2">Berkas saat ini: <a href="index.php?page=file&source=submission&id=' . (int) $submission['id'] . '" target="_blank"><b>' . e($submission['original_name']) . '</b></a></p>';
                }
                echo '<button class="btn span-2" type="submit">Simpan dan Kumpulkan</button></form>';
                modal_end();
                $assignmentModals .= (string) ob_get_clean();
            } else {
                $status = '<button class="btn btn-secondary btn-small" data-modal="submissions-' . (int) $assignment['id'] . '">' . (int) $assignment['submission_count'] . ' kiriman</button>';
                ob_start();
                render_submissions_modal($assignment, $canManage);
                $assignmentModals .= (string) ob_get_clean();
            }
            echo '<tr><td><div class="table-title">' . e($assignment['title']) . '</div><div class="table-subtitle">Maksimal ' . e($assignment['max_points']) . ' poin</div></td><td>' . e(strtoupper($assignment['assignment_type'])) . '</td><td>' . e($assignment['outcome_code'] ?: '—') . '</td><td>' . e($assignment['weight']) . '%</td><td>' . e($assignment['due_at'] ? date('d M Y, H:i', strtotime($assignment['due_at'])) : 'Tanpa batas') . '</td><td class="actions">' . $status . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</article>';
    echo $assignmentModals;
}

function render_submissions_modal(array $assignment, bool $canManage): void
{
    modal_start('submissions-' . (int) $assignment['id'], 'Kiriman: ' . $assignment['title']);
    $stmt = db()->prepare('SELECT s.*,u.full_name,u.identity_number FROM submissions s JOIN users u ON u.id=s.student_id WHERE s.assignment_id=? ORDER BY s.submitted_at DESC');
    $stmt->execute([$assignment['id']]);
    $submissions = $stmt->fetchAll();
    if (!$submissions) {
        empty_state('Belum ada kiriman', 'Mahasiswa belum mengumpulkan tugas ini.');
    } else {
        foreach ($submissions as $submission) {
            echo '<article class="panel"><div class="panel-head"><div><h2>' . e($submission['full_name']) . '</h2><p>' . e($submission['identity_number']) . ' · ' . e(date('d M Y, H:i', strtotime($submission['submitted_at']))) . ($submission['is_late'] ? ' · Terlambat' : '') . '</p></div>' . status_badge($submission['status']) . '</div><div class="panel-body">';
            if ($submission['submission_text']) {
                echo '<p style="white-space:pre-wrap">' . e($submission['submission_text']) . '</p>';
            }
            if ($submission['file_path']) {
                echo '<p><a class="btn btn-secondary btn-small" href="index.php?page=file&source=submission&id=' . (int) $submission['id'] . '" target="_blank">Buka ' . e($submission['original_name']) . '</a></p>';
            }
            if ($canManage) {
                echo '<form method="post" class="form-grid"><input type="hidden" name="action" value="grade_submission"><input type="hidden" name="return_tab" value="assignments"><input type="hidden" name="submission_id" value="' . (int) $submission['id'] . '">' . csrf_field() . '<label>Nilai (maks. ' . e($assignment['max_points']) . ')<input type="number" step="0.01" min="0" max="' . e($assignment['max_points']) . '" name="score" required value="' . e($submission['score']) . '"></label><label>Umpan balik<textarea name="feedback">' . e($submission['feedback']) . '</textarea></label><button class="btn span-2" type="submit">Simpan Penilaian</button></form>';
            }
            echo '</div></article>';
        }
    }
    modal_end();
}

function render_discussions_tab(array $class, array $user, bool $canManage): void
{
    $stmt = db()->prepare('SELECT t.*,u.full_name,u.role,(SELECT COUNT(*) FROM discussion_posts p WHERE p.thread_id=t.id) reply_count FROM discussion_threads t JOIN users u ON u.id=t.created_by WHERE t.class_id=? ORDER BY t.is_pinned DESC,t.created_at DESC');
    $stmt->execute([$class['id']]);
    $threads = $stmt->fetchAll();
    echo '<article class="panel"><div class="panel-head"><div><h2>Forum Diskusi Kelas</h2><p>Tanya jawab dan kolaborasi akademik yang terdokumentasi</p></div><button class="btn" data-modal="new-discussion">+ Topik Baru</button></div><div class="panel-body">';
    if (!$threads) {
        empty_state('Belum ada diskusi', 'Mulai topik untuk membahas materi atau mengajukan pertanyaan.');
    } else {
        foreach ($threads as $thread) {
            echo '<article class="panel"><div class="panel-head"><div><h2>' . e($thread['title']) . '</h2><p>' . e($thread['full_name']) . ' · ' . e(role_label($thread['role'])) . ' · ' . e(date('d M Y, H:i', strtotime($thread['created_at']))) . '</p></div>' . ($thread['is_pinned'] ? '<span class="badge badge-active">Disematkan</span>' : '<span class="badge">' . (int) $thread['reply_count'] . ' tanggapan</span>') . '</div><div class="panel-body"><p style="white-space:pre-wrap;line-height:1.7">' . e($thread['body']) . '</p>';
            $posts = db()->prepare('SELECT p.*,u.full_name,u.role FROM discussion_posts p JOIN users u ON u.id=p.created_by WHERE p.thread_id=? ORDER BY p.created_at');
            $posts->execute([$thread['id']]);
            foreach ($posts->fetchAll() as $post) {
                echo '<div class="quick-item" style="align-items:flex-start;margin-top:9px"><span class="avatar">' . e(mb_strtoupper(mb_substr($post['full_name'], 0, 1))) . '</span><span><b>' . e($post['full_name']) . ' <small>· ' . e(role_label($post['role'])) . '</small></b><small>' . e(date('d M Y, H:i', strtotime($post['created_at']))) . '</small><p style="white-space:pre-wrap;margin-bottom:0">' . e($post['body']) . '</p></span></div>';
            }
            if (!$thread['is_locked']) {
                echo '<form method="post" class="form-grid" style="margin-top:15px">' . csrf_field() . '<input type="hidden" name="action" value="add_discussion_post"><input type="hidden" name="return_tab" value="discussions"><input type="hidden" name="thread_id" value="' . (int) $thread['id'] . '"><label class="span-2">Tanggapan<textarea name="body" required placeholder="Tulis tanggapan yang relevan dan santun"></textarea></label><button class="btn span-2">Kirim Tanggapan</button></form>';
            }
            echo '</div></article>';
        }
    }
    echo '</div></article>';
    modal_start('new-discussion', 'Buka Topik Diskusi');
    echo '<form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="new_discussion"><input type="hidden" name="return_tab" value="discussions"><label class="span-2">Judul topik<input name="title" required></label><label class="span-2">Pertanyaan atau uraian<textarea name="body" required></textarea></label>';
    if ($canManage) {
        echo '<label class="span-2" style="display:flex;grid-template-columns:auto 1fr;align-items:center"><input type="checkbox" name="is_pinned" style="width:auto"> Sematkan topik</label>';
    }
    echo '<button class="btn span-2">Terbitkan Topik</button></form>';
    modal_end();
}

function render_gradebook_tab(array $class, array $assignments, array $roster, array $user, bool $canManage): void
{
    echo '<article class="panel"><div class="panel-head"><div><h2>Buku Nilai</h2><p>Nilai berbobot dan hasil akhir kelas</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Mahasiswa</th>';
    foreach ($assignments as $assignment) {
        echo '<th>' . e(mb_strimwidth($assignment['title'], 0, 24, '…')) . '<div class="table-subtitle">' . e($assignment['weight']) . '%</div></th>';
    }
    echo '<th>Nilai Akhir</th><th>Huruf</th></tr></thead><tbody>';
    $visibleRoster = $user['role'] === 'mahasiswa' ? array_values(array_filter($roster, fn ($student) => (int) $student['id'] === (int) $user['id'])) : $roster;
    foreach ($visibleRoster as $student) {
        $total = 0.0;
        $totalWeight = 0.0;
        echo '<tr><td><div class="table-title">' . e($student['full_name']) . '</div><div class="table-subtitle">' . e($student['identity_number']) . '</div></td>';
        foreach ($assignments as $assignment) {
            $stmt = db()->prepare('SELECT score FROM submissions WHERE assignment_id=? AND student_id=?');
            $stmt->execute([$assignment['id'], $student['id']]);
            $score = $stmt->fetchColumn();
            if ($score !== false && $score !== null) {
                $normalized = ((float) $score / max(1, (float) $assignment['max_points'])) * 100;
                $weight = (float) $assignment['weight'];
                $total += $normalized * ($weight / 100);
                $totalWeight += $weight;
                echo '<td><b>' . e(number_format((float) $score, 1)) . '</b></td>';
            } else {
                echo '<td><span class="table-subtitle">—</span></td>';
            }
        }
        $final = $totalWeight > 0 ? round($total * (100 / $totalWeight), 2) : 0;
        echo '<td><b>' . ($totalWeight ? e(number_format($final, 2)) : '—') . '</b></td><td>' . ($totalWeight ? '<span class="badge badge-active">' . grade_letter($final) . '</span>' : '—') . '</td></tr>';
    }
    if (!$visibleRoster) {
        echo '<tr><td colspan="' . (count($assignments) + 3) . '">Belum ada mahasiswa terdaftar.</td></tr>';
    }
    echo '</tbody></table></div></article><div class="alert alert-info">Nilai akhir dihitung proporsional berdasarkan bobot asesmen yang sudah memiliki nilai. Pastikan total bobot asesmen mencapai 100% sebelum nilai disahkan.</div>';
}

function render_outcomes_tab(array $class, array $outcomes, bool $canManage): void
{
    $action = $canManage ? '<button class="btn" data-modal="outcome-modal">+ Tambah CPMK</button>' : '';
    echo '<article class="panel"><div class="panel-head"><div><h2>Capaian Pembelajaran Mata Kuliah</h2><p>Pemetaan asesmen terhadap CPMK berbasis OBE</p></div>' . $action . '</div>';
    if (!$outcomes) {
        empty_state('CPMK belum ditambahkan', 'Tambahkan CPMK agar setiap asesmen dapat dipetakan terhadap capaian pembelajaran.');
    } else {
        echo '<div class="table-wrap"><table class="data-table"><thead><tr><th>Kode</th><th>Deskripsi CPMK</th><th>Bobot</th><th>Asesmen</th><th>Rata-rata Capaian</th></tr></thead><tbody>';
        foreach ($outcomes as $outcome) {
            $stmt = db()->prepare('SELECT COUNT(DISTINCT a.id) assessments,AVG((s.score/a.max_points)*100) achievement FROM assignments a LEFT JOIN submissions s ON s.assignment_id=a.id WHERE a.class_id=? AND a.outcome_id=?');
            $stmt->execute([$class['id'], $outcome['id']]);
            $metric = $stmt->fetch();
            $achievement = $metric['achievement'] !== null ? round((float) $metric['achievement'], 1) : null;
            echo '<tr><td><b>' . e($outcome['code']) . '</b></td><td>' . e($outcome['description']) . '</td><td>' . e($outcome['weight']) . '%</td><td>' . (int) $metric['assessments'] . '</td><td>' . ($achievement !== null ? '<div class="metric-row"><b>' . e($achievement) . '%</b><div class="progress"><i style="width:' . min(100, $achievement) . '%"></i></div></div>' : '—') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</article>';
}

function render_class_modals(array $class, array $meetings, array $outcomes): void
{
    modal_start('meeting-modal', 'Tambah Pertemuan');
    echo '<form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="add_meeting"><input type="hidden" name="return_tab" value="meetings"><label>Nomor pertemuan<input type="number" name="meeting_number" min="1" max="24" required value="' . (count($meetings) + 1) . '"></label><label>Tanggal<input type="date" name="meeting_date" required value="' . e(date('Y-m-d')) . '"></label><label class="span-2">Judul pertemuan<input name="title" required placeholder="Topik utama perkuliahan"></label><label class="span-2">Deskripsi<textarea name="description" placeholder="Tujuan dan aktivitas pembelajaran"></textarea></label><label>Mulai<input type="time" name="starts_at"></label><label>Selesai<input type="time" name="ends_at"></label><label>Moda<select name="mode"><option value="offline">Offline</option><option value="online">Online</option><option value="hybrid" selected>Hybrid</option></select></label><label>Jurnal mengajar<textarea name="journal" placeholder="Realisasi pembelajaran"></textarea></label><button class="btn span-2" type="submit">Simpan Pertemuan</button></form>';
    modal_end();

    modal_start('material-modal', 'Tambah Materi Pembelajaran');
    echo '<form method="post" enctype="multipart/form-data" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="add_material"><input type="hidden" name="return_tab" value="materials"><label class="span-2">Judul materi<input name="title" required></label><label>Pertemuan<select name="meeting_id"><option value="">Materi umum</option>';
    foreach ($meetings as $meeting) {
        echo '<option value="' . (int) $meeting['id'] . '">Pertemuan ' . (int) $meeting['meeting_number'] . ' — ' . e($meeting['title']) . '</option>';
    }
    echo '</select></label><label>Jenis konten<select name="material_type"><option value="link">Tautan</option><option value="video">Video</option><option value="text">Teks</option></select></label><label class="span-2">Deskripsi<textarea name="description"></textarea></label><label class="span-2">Tautan atau isi teks<textarea name="content" placeholder="https://... atau tulis isi materi"></textarea></label><label class="span-2">Atau unggah berkas<input type="file" name="material_file"><small>Apabila berkas dipilih, jenis materi otomatis menjadi berkas.</small></label><label class="span-2">Tersedia mulai<input type="datetime-local" name="available_at"></label><button class="btn span-2" type="submit">Terbitkan Materi</button></form>';
    modal_end();

    modal_start('assignment-modal', 'Buat Tugas atau Ujian');
    echo '<form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="add_assignment"><input type="hidden" name="return_tab" value="assignments"><label class="span-2">Judul asesmen<input name="title" required></label><label>Jenis<select name="assignment_type"><option value="individual">Tugas Individu</option><option value="group">Tugas Kelompok</option><option value="quiz">Kuis</option><option value="uts">UTS</option><option value="uas">UAS</option><option value="project">Proyek</option></select></label><label>CPMK<select name="outcome_id"><option value="">Tidak dipetakan</option>';
    foreach ($outcomes as $outcome) {
        echo '<option value="' . (int) $outcome['id'] . '">' . e($outcome['code']) . ' — ' . e(mb_strimwidth($outcome['description'], 0, 50, '…')) . '</option>';
    }
    echo '</select></label><label class="span-2">Petunjuk pengerjaan<textarea name="instructions"></textarea></label><label>Dibuka<input type="datetime-local" name="opens_at"></label><label>Tenggat<input type="datetime-local" name="due_at"></label><label>Nilai maksimal<input type="number" step="0.01" name="max_points" value="100" min="1" required></label><label>Bobot nilai (%)<input type="number" step="0.01" name="weight" value="0" min="0" max="100" required></label><label>Maksimal pengumpulan<input type="number" name="max_attempts" value="1" min="1" max="10"></label><label style="display:flex;align-items:center;grid-template-columns:auto 1fr;margin-top:25px"><input type="checkbox" name="allow_late" checked style="width:auto"> Izinkan pengumpulan terlambat</label><button class="btn span-2" type="submit">Terbitkan Tugas/Ujian</button></form>';
    modal_end();

    modal_start('outcome-modal', 'Tambah CPMK');
    echo '<form method="post" class="form-grid">' . csrf_field() . '<input type="hidden" name="action" value="add_outcome"><input type="hidden" name="return_tab" value="outcomes"><label>Kode CPMK<input name="code" required placeholder="CPMK-1"></label><label>Bobot (%)<input type="number" step="0.01" name="weight" value="0" min="0" max="100"></label><label class="span-2">Deskripsi capaian<textarea name="description" required></textarea></label><button class="btn span-2" type="submit">Simpan CPMK</button></form>';
    modal_end();
}

function page_calendar(array $user): void
{
    $classes = fetch_user_classes($user);
    $ids = array_column($classes, 'id');
    $events = [];
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare('SELECT m.meeting_date event_date,m.starts_at event_time,m.title,"Pertemuan" event_type,c.id class_id,co.name course_name FROM class_meetings m JOIN classes c ON c.id=m.class_id JOIN courses co ON co.id=c.course_id WHERE c.id IN (' . $placeholders . ') AND m.meeting_date>=CURDATE() UNION ALL SELECT DATE(a.due_at),TIME(a.due_at),a.title,"Tenggat",c.id,co.name FROM assignments a JOIN classes c ON c.id=a.class_id JOIN courses co ON co.id=c.course_id WHERE c.id IN (' . $placeholders . ') AND a.due_at>=NOW() ORDER BY event_date,event_time LIMIT 80');
        $stmt->execute(array_merge($ids, $ids));
        $events = $stmt->fetchAll();
    }
    render_header('Kalender Akademik');
    page_heading('Kalender Akademik', 'Jadwal pertemuan dan tenggat tugas dari seluruh kelas Anda.');
    echo '<article class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Tanggal</th><th>Waktu</th><th>Kegiatan</th><th>Mata Kuliah</th><th>Jenis</th></tr></thead><tbody>';
    foreach ($events as $event) {
        echo '<tr><td><b>' . e(date('d F Y', strtotime($event['event_date']))) . '</b></td><td>' . e($event['event_time'] ? substr($event['event_time'], 0, 5) : '—') . '</td><td><a class="table-title" href="index.php?page=class&id=' . (int) $event['class_id'] . '">' . e($event['title']) . '</a></td><td>' . e($event['course_name']) . '</td><td><span class="badge">' . e($event['event_type']) . '</span></td></tr>';
    }
    if (!$events) {
        echo '<tr><td colspan="5">Belum ada jadwal mendatang.</td></tr>';
    }
    echo '</tbody></table></div></article>';
    render_footer();
}

function page_notifications(array $user): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        db()->prepare('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')->execute([$user['id']]);
        flash('success', 'Semua notifikasi ditandai sudah dibaca.');
        redirect('index.php?page=notifications');
    }
    $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([$user['id']]);
    $items = $stmt->fetchAll();
    render_header('Notifikasi');
    page_heading('Notifikasi', 'Pembaruan aktivitas akademik dan pembelajaran.', '<form method="post">' . csrf_field() . '<button class="btn btn-secondary" type="submit">Tandai Semua Dibaca</button></form>');
    echo '<article class="panel"><div class="quick-list panel-body">';
    foreach ($items as $item) {
        echo '<a class="quick-item" href="' . e($item['link'] ?: '#') . '"><span class="quick-icon">' . ($item['read_at'] ? '◇' : '●') . '</span><span><b>' . e($item['title']) . '</b><small>' . e($item['message']) . ' · ' . e(date('d M Y, H:i', strtotime($item['created_at']))) . '</small></span></a>';
    }
    if (!$items) {
        empty_state('Belum ada notifikasi', 'Pembaruan penting akan muncul di sini.');
    }
    echo '</div></article>';
    render_footer();
}

function page_profile(array $user): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name = trim((string) $_POST['full_name']);
        $email = trim((string) $_POST['email']);
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Nama dan email harus valid.');
        }
        db()->prepare('UPDATE users SET full_name=?,email=?,phone=?,updated_at=NOW() WHERE id=?')->execute([$name, $email, trim((string) ($_POST['phone'] ?? '')), $user['id']]);
        if (!empty($_POST['new_password'])) {
            if (!password_verify((string) $_POST['current_password'], $user['password_hash'])) {
                throw new RuntimeException('Kata sandi saat ini tidak sesuai.');
            }
            if (strlen((string) $_POST['new_password']) < 10) {
                throw new RuntimeException('Kata sandi baru minimal 10 karakter.');
            }
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash((string) $_POST['new_password'], PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
        }
        audit('update', 'users', (int) $user['id'], 'Memperbarui profil');
        flash('success', 'Profil berhasil diperbarui.');
        redirect('index.php?page=profile');
    }
    render_header('Profil Saya');
    page_heading('Profil Saya', 'Kelola identitas dan keamanan akun Anda.');
    echo '<section class="grid-2"><article class="panel"><div class="panel-head"><h2>Informasi Akun</h2></div><div class="panel-body"><form method="post" class="form-grid">' . csrf_field() . '<label class="span-2">Nama lengkap<input name="full_name" required value="' . e($user['full_name']) . '"></label><label>Username<input disabled value="' . e($user['username']) . '"></label><label>Peran<input disabled value="' . e(role_label($user['role'])) . '"></label><label>Email<input type="email" name="email" required value="' . e($user['email']) . '"></label><label>Nomor telepon<input name="phone" value="' . e($user['phone']) . '"></label><div class="section-label">Ganti Kata Sandi (opsional)</div><label>Kata sandi saat ini<input type="password" name="current_password"></label><label>Kata sandi baru<input type="password" name="new_password" minlength="10"></label><button class="btn span-2" type="submit">Simpan Perubahan</button></form></div></article><aside><article class="panel"><div class="panel-head"><h2>Status Akun</h2></div><div class="panel-body metric-list"><div class="metric-row"><span>Status</span><b class="ok">Aktif</b></div><div class="metric-row"><span>Program Studi</span><b>' . e($user['program_name'] ?: 'Institusi') . '</b></div><div class="metric-row"><span>Login terakhir</span><b>' . e($user['last_login_at'] ? date('d M Y, H:i', strtotime($user['last_login_at'])) : 'Pertama kali') . '</b></div></div></article></aside></section>';
    render_footer();
}
