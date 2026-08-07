<?php
declare(strict_types=1);

function nav_items(array $user): array
{
    $items = [
        ['dashboard', 'Beranda', '⌂'],
        ['my-classes', $user['role'] === 'mahasiswa' ? 'Mata Kuliah Saya' : 'Kelas Pembelajaran', '▦'],
        ['calendar', 'Kalender Akademik', '◫'],
    ];
    if (in_array($user['role'], ['super_admin', 'admin'], true)) {
        $items = array_merge($items, [
            ['users', 'Pengguna', '♙'],
            ['programs', 'Program Studi', '◇'],
            ['courses', 'Mata Kuliah', '□'],
            ['classes', 'Kelas & Peserta', '▤'],
            ['academic-years', 'Tahun Akademik', '◷'],
            ['announcements', 'Pengumuman', '◉'],
        ]);
    } elseif ($user['role'] === 'kaprodi') {
        $items = array_merge($items, [
            ['courses', 'Kurikulum & Mata Kuliah', '□'],
            ['classes', 'Monitoring Kelas', '▤'],
        ]);
    }
    if (in_array($user['role'], ['super_admin', 'admin', 'kaprodi', 'lpm'], true)) {
        $items[] = ['reports', 'Laporan & Mutu', '◔'];
    }
    if (in_array($user['role'], ['super_admin', 'admin'], true)) {
        $items[] = ['api-tokens', 'Integrasi API', '⇄'];
        $items[] = ['audit', 'Audit Aktivitas', '⌕'];
        $items[] = ['settings', 'Pengaturan Institusi', '⚙'];
    }
    return $items;
}

function render_header(string $title, array $options = []): void
{
    $user = current_user();
    $page = (string) ($_GET['page'] ?? 'dashboard');
    $institution = setting('institution_short_name', 'STAI Al-Jawami');
    $primary = setting('primary_color', '#08784f');
    $secondary = setting('secondary_color', '#d5a328');
    $logo = setting('logo_path', '');
    $logoVersion = $logo ? substr(hash('sha256', (string) $logo), 0, 12) : '';
    $unread = 0;
    if ($user) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL');
        $stmt->execute([$user['id']]);
        $unread = (int) $stmt->fetchColumn();
    }
    ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?= e($primary) ?>">
    <title><?= e($title) ?> · <?= e($institution) ?></title>
    <link rel="stylesheet" href="assets/app.css?v=1.0.2">
    <style>:root{--primary:<?= e($primary) ?>;--secondary:<?= e($secondary) ?>}</style>
</head>
<body>
<?php if (!$user): ?>
    <main class="public-shell">
<?php else: ?>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="index.php">
                <?php if ($logo): ?><img src="index.php?page=file&amp;type=logo&amp;v=<?= e($logoVersion) ?>" alt="Logo <?= e($institution) ?>"><?php else: ?><span class="brand-mark">AJ</span><?php endif; ?>
                <span><b>LMS</b><small><?= e($institution) ?></small></span>
            </a>
            <nav aria-label="Navigasi utama">
                <?php foreach (nav_items($user) as [$key, $label, $icon]): ?>
                    <a class="nav-link <?= $page === $key ? 'active' : '' ?>" href="index.php?page=<?= e($key) ?>"><span><?= $icon ?></span><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <div class="user-mini"><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></span><span><b><?= e($user['full_name']) ?></b><small><?= e(role_label($user['role'])) ?></small></span></div>
                <a href="index.php?page=logout">Keluar</a>
            </div>
        </aside>
        <div class="app-main">
            <header class="topbar">
                <button class="icon-button menu-button" type="button" data-toggle-menu aria-label="Buka menu">☰</button>
                <div class="topbar-title"><small><?= e(date('l, d F Y')) ?></small><b><?= e($title) ?></b></div>
                <div class="top-actions">
                    <a class="icon-button notification" href="index.php?page=notifications" aria-label="Notifikasi">♢<?php if ($unread): ?><span><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?></a>
                    <a class="profile-pill" href="index.php?page=profile"><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></span><span><b><?= e($user['full_name']) ?></b><small><?= e(role_label($user['role'])) ?></small></span></a>
                </div>
            </header>
            <main class="content">
                <?php foreach (take_flashes() as $flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?>" data-alert><?= e($flash['message']) ?><button type="button" aria-label="Tutup">×</button></div>
                <?php endforeach; ?>
<?php endif; ?>
    <?php
}

function render_footer(): void
{
    $user = current_user();
    if (!$user) {
        echo '</main>';
    } else {
        echo '</main><footer class="footer"><span>© ' . date('Y') . ' ' . e(setting('institution_name', 'STAI Yapata Al-Jawami Bandung')) . '</span><span>' . e(setting('motto', 'Profesional – Unggul – Mandiri – Berakhlakul Karimah')) . '</span></footer></div></div>';
    }
    echo '<script src="assets/app.js?v=1.0.2"></script></body></html>';
}

function page_heading(string $title, string $subtitle = '', string $actions = ''): void
{
    echo '<div class="page-heading"><div><h1>' . e($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p>' . e($subtitle) . '</p>';
    }
    echo '</div><div class="heading-actions">' . $actions . '</div></div>';
}

function stat_card(string $label, string|int $value, string $note, string $tone = 'green'): string
{
    return '<article class="stat-card ' . e($tone) . '"><div class="stat-top"><span>' . e($label) . '</span><i></i></div><strong>' . e($value) . '</strong><small>' . e($note) . '</small></article>';
}

function status_badge(string $status): string
{
    $friendly = [
        'active' => 'Aktif', 'draft' => 'Draf', 'archived' => 'Diarsipkan',
        'submitted' => 'Dikumpulkan', 'graded' => 'Dinilai', 'revision' => 'Revisi',
        'approved' => 'Disetujui', 'offline' => 'Offline', 'online' => 'Online',
        'hybrid' => 'Hybrid', 'hadir' => 'Hadir', 'terlambat' => 'Terlambat',
        'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa',
    ][$status] ?? ucfirst($status);
    return '<span class="badge badge-' . e($status) . '">' . e($friendly) . '</span>';
}

function empty_state(string $title, string $message): void
{
    echo '<div class="empty"><div class="empty-icon">◇</div><h3>' . e($title) . '</h3><p>' . e($message) . '</p></div>';
}

function modal_start(string $id, string $title): void
{
    echo '<dialog id="' . e($id) . '" class="modal"><form method="dialog" class="modal-close"><button aria-label="Tutup">×</button></form><div class="modal-head"><h2>' . e($title) . '</h2></div><div class="modal-body">';
}

function modal_end(): void
{
    echo '</div></dialog>';
}
