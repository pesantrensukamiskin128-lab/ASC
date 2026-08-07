<?php
declare(strict_types=1);

function seed_database(PDO $pdo, array $admin): void
{
    $settings = [
        'institution_name' => 'STAI Yapata Al-Jawami Bandung',
        'institution_short_name' => 'STAI Al-Jawami',
        'motto' => 'Profesional – Unggul – Mandiri – Berakhlakul Karimah',
        'address' => 'Komplek Pesantren Al-Jawami No. 87 Cileunyi Bandung 40622',
        'website' => 'www.stai-aljawami.ac.id',
        'email' => 'info@stai-aljawami.ac.id',
        'primary_color' => '#08784f',
        'secondary_color' => '#d5a328',
        'logo_path' => '',
        'attendance_weight' => '20',
        'assignment_weight' => '35',
        'exam_weight' => '45',
        'api_allowed_origin' => '',
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW())');
    foreach ($settings as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    $programs = [
        ['PAI', 'Pendidikan Agama Islam', '#138a55'],
        ['HESY', 'Hukum Ekonomi Syariah', '#c73e3e'],
        ['PIAUD', 'Pendidikan Islam Anak Usia Dini', '#dc5c9d'],
        ['PSI', 'Psikologi Islam', '#57327c'],
        ['MHU', 'Manajemen Haji dan Umrah', '#8a5a3b'],
        ['KPI', 'Komunikasi dan Penyiaran Islam', '#2176ae'],
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO programs (code,name,degree,color,is_active) VALUES (?,?,"S1",?,1)');
    foreach ($programs as $program) {
        $stmt->execute($program);
    }

    $yearName = date('Y') . '/' . (date('Y') + 1);
    $pdo->prepare('INSERT IGNORE INTO academic_years (name,semester,starts_on,ends_on,is_active) VALUES (?,"ganjil",?,?,1)')
        ->execute([$yearName, date('Y') . '-08-01', (date('Y') + 1) . '-01-31']);

    $stmt = $pdo->prepare('INSERT INTO users (username,email,password_hash,full_name,identity_number,role,is_active,created_at) VALUES (?,?,?,?,?,"super_admin",1,NOW()) ON DUPLICATE KEY UPDATE email=VALUES(email),password_hash=VALUES(password_hash),full_name=VALUES(full_name),is_active=1,updated_at=NOW()');
    $stmt->execute([
        $admin['username'],
        $admin['email'],
        password_hash($admin['password'], PASSWORD_DEFAULT),
        $admin['full_name'],
        'ADMIN-001',
    ]);
}

function seed_demo_database(PDO $pdo): void
{
    $programId = (int) $pdo->query('SELECT id FROM programs WHERE code="PAI"')->fetchColumn();
    $yearId = (int) $pdo->query('SELECT id FROM academic_years WHERE is_active=1 LIMIT 1')->fetchColumn();
    $password = password_hash('Demo!2026#', PASSWORD_DEFAULT);
    $users = [
        ['demo.dosen', 'dosen@demo.local', 'Dr. Ahmad Fathoni, M.Pd.', '2101018001', 'dosen'],
        ['demo.kaprodi', 'kaprodi@demo.local', 'Dr. Siti Rahmah, M.Pd.', '2102028002', 'kaprodi'],
        ['2026110001', 'mhs1@demo.local', 'Aulia Rahman', '2026110001', 'mahasiswa'],
        ['2026110002', 'mhs2@demo.local', 'Nabila Putri', '2026110002', 'mahasiswa'],
        ['2026110003', 'mhs3@demo.local', 'Fajar Maulana', '2026110003', 'mahasiswa'],
        ['2026110004', 'mhs4@demo.local', 'Salsabila Nur', '2026110004', 'mahasiswa'],
        ['2026110005', 'mhs5@demo.local', 'Rizky Hidayat', '2026110005', 'mahasiswa'],
        ['2026110006', 'mhs6@demo.local', 'Zahra Aini', '2026110006', 'mahasiswa'],
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO users (external_id,username,email,password_hash,full_name,identity_number,role,program_id,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,1,NOW())');
    foreach ($users as $row) {
        $stmt->execute(['DEMO-' . $row[0], $row[0], $row[1], $password, $row[2], $row[3], $row[4], $programId]);
    }
    $lecturerId = (int) $pdo->query('SELECT id FROM users WHERE username="demo.dosen"')->fetchColumn();
    $courseRows = [
        ['PAI-101', 'Pengantar Studi Islam', 2, 1, 'Fondasi studi keislaman dan tradisi akademik.'],
        ['PAI-203', 'Strategi Pembelajaran PAI', 3, 3, 'Perencanaan dan praktik strategi pembelajaran PAI.'],
        ['PAI-305', 'Evaluasi Pembelajaran', 3, 5, 'Konsep, instrumen, dan analisis evaluasi pendidikan.'],
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO courses (program_id,code,name,credits,semester_number,description,is_active,created_at) VALUES (?,?,?,?,?,?,1,NOW())');
    foreach ($courseRows as $course) {
        $stmt->execute([$programId, ...$course]);
    }
    $courseId = (int) $pdo->query('SELECT id FROM courses WHERE code="PAI-101"')->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO classes (course_id,program_id,academic_year_id,lecturer_id,name,mode,room,meeting_url,schedule_day,schedule_time,syllabus_status,status,created_at) VALUES (?,?,?,?,"A","hybrid","Ruang 2.1","https://meet.google.com/","Sabtu","08.00–10.30 WIB","approved","active",NOW())');
    $stmt->execute([$courseId, $programId, $yearId, $lecturerId]);
    $classId = (int) $pdo->lastInsertId();
    $studentIds = $pdo->query('SELECT id FROM users WHERE external_id LIKE "DEMO-%" AND role="mahasiswa"')->fetchAll(PDO::FETCH_COLUMN);
    $enroll = $pdo->prepare('INSERT INTO enrollments (class_id,student_id,status,enrolled_at) VALUES (?,? ,"active",NOW())');
    foreach ($studentIds as $studentId) {
        $enroll->execute([$classId, $studentId]);
    }
    $meeting = $pdo->prepare('INSERT INTO class_meetings (class_id,meeting_number,title,description,meeting_date,starts_at,ends_at,mode,attendance_token,attendance_opens_at,attendance_closes_at,journal,is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)');
    for ($i = 1; $i <= 3; $i++) {
        $date = date('Y-m-d', strtotime('-' . (21 - ($i * 7)) . ' days'));
        $meeting->execute([$classId, $i, ['Orientasi dan Kontrak Kuliah', 'Hakikat dan Ruang Lingkup Studi Islam', 'Sumber Ajaran Islam'][($i - 1)], 'Pertemuan contoh untuk demonstrasi alur pembelajaran.', $date, '08:00:00', '10:30:00', 'hybrid', 'AJW' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), $date . ' 07:45:00', $date . ' 10:00:00', 'Pembelajaran terlaksana sesuai RPS.', 1]);
        $meetingId = (int) $pdo->lastInsertId();
        $att = $pdo->prepare('INSERT INTO attendance (meeting_id,student_id,status,attendance_mode,checked_in_at,recorded_by) VALUES (?,?,"hadir","online",NOW(),?)');
        foreach ($studentIds as $studentId) {
            $att->execute([$meetingId, $studentId, $lecturerId]);
        }
    }
    $firstMeetingId = (int) $pdo->query('SELECT id FROM class_meetings WHERE class_id=' . $classId . ' ORDER BY meeting_number LIMIT 1')->fetchColumn();
    $pdo->prepare('INSERT INTO materials (class_id,meeting_id,title,description,material_type,content,is_published,created_by,created_at) VALUES (?,?,?,?,"text",?,1,?,NOW())')->execute([$classId, $firstMeetingId, 'Panduan Perkuliahan', 'Ringkasan kontrak dan tata tertib kelas.', 'Selamat datang di kelas Pengantar Studi Islam. Pelajari RPS, ikuti seluruh pertemuan, dan gunakan forum kelas secara bertanggung jawab.', $lecturerId]);
    $pdo->prepare('INSERT INTO course_outcomes (course_id,code,description,weight,created_at) VALUES (?,"CPMK-1","Mahasiswa mampu menjelaskan ruang lingkup studi Islam secara sistematis.",50,NOW()), (?,"CPMK-2","Mahasiswa mampu menganalisis sumber ajaran Islam dalam konteks akademik.",50,NOW())')->execute([$courseId, $courseId]);
    $outcomeId = (int) $pdo->query('SELECT id FROM course_outcomes WHERE course_id=' . $courseId . ' ORDER BY id LIMIT 1')->fetchColumn();
    $pdo->prepare('INSERT INTO assignments (class_id,outcome_id,title,instructions,assignment_type,opens_at,due_at,max_points,weight,allow_late,max_attempts,is_published,created_by,created_at) VALUES (?,?,"Esai Ruang Lingkup Studi Islam","Tuliskan esai 750–1.000 kata dengan sedikitnya tiga referensi ilmiah.","individual",NOW(),DATE_ADD(NOW(),INTERVAL 7 DAY),100,20,1,2,1,?,NOW())')->execute([$classId, $outcomeId, $lecturerId]);
    $pdo->prepare('INSERT INTO announcements (title,body,audience_role,is_pinned,published_at,created_by) VALUES ("Selamat Datang di LMS STAI Al-Jawami","Data contoh telah tersedia. Administrator dapat menghapus atau menggantinya dengan data akademik sebenarnya.","all",1,NOW(),?)')->execute([$lecturerId]);
}
