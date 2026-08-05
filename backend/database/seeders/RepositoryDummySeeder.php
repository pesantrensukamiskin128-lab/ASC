<?php

namespace Database\Seeders;

use App\Models\Lecturer;
use App\Models\LecturerWork;
use App\Models\Penelitian;
use App\Models\PenelitianMember;
use App\Models\PenelitianPeriod;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\Thesis;
use App\Models\ThesisSupervisor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RepositoryDummySeeder extends Seeder
{
    public function run(): void
    {
        $admin    = User::where('email', 'superadmin@jawami.ac.id')->first();
        $lecturer = Lecturer::first();
        $prodi    = StudyProgram::first();

        if (!$admin || !$lecturer) {
            $this->command->warn('Jalankan UserSeeder dan LecturerSeeder terlebih dahulu.');
            return;
        }

        // =============================================
        // 1. PERIODE HIBAH
        // =============================================
        $periods = [];
        foreach ([2024, 2025, 2026] as $year) {
            $periods[$year] = PenelitianPeriod::firstOrCreate(
                ['name' => "Hibah Penelitian {$year}", 'type' => 'penelitian'],
                ['year' => $year, 'is_active' => $year === 2025]
            );
            PenelitianPeriod::firstOrCreate(
                ['name' => "Hibah Pengabdian {$year}", 'type' => 'pengabdian'],
                ['year' => $year, 'is_active' => $year === 2025]
            );
        }

        // =============================================
        // 2. PENELITIAN & PENGABDIAN (is_published)
        // =============================================
        $penelitianData = [
            [
                'type'    => 'penelitian',
                'title'   => 'Implementasi Machine Learning untuk Deteksi Penyakit Tanaman Padi Berbasis Citra Digital',
                'abstract'=> 'Penelitian ini mengimplementasikan algoritma Convolutional Neural Network (CNN) untuk mendeteksi penyakit pada tanaman padi berdasarkan citra daun. Dataset terdiri dari 5.000 gambar dengan 5 kategori penyakit. Hasil pengujian menunjukkan akurasi 94.7% pada data uji.',
                'keywords'=> 'machine learning, CNN, penyakit padi, pertanian cerdas',
                'year'    => 2024,
            ],
            [
                'type'    => 'penelitian',
                'title'   => 'Pengembangan Sistem Informasi Akademik Terintegrasi Berbasis Microservices',
                'abstract'=> 'Penelitian ini merancang dan mengimplementasikan arsitektur microservices untuk sistem informasi akademik perguruan tinggi. Sistem mampu menangani 10.000 pengguna bersamaan dengan response time rata-rata 150ms.',
                'keywords'=> 'microservices, sistem informasi, akademik, REST API',
                'year'    => 2025,
            ],
            [
                'type'    => 'penelitian',
                'title'   => 'Analisis Keamanan Jaringan IoT pada Smart Campus menggunakan Intrusion Detection System',
                'abstract'=> 'Penelitian menganalisis kerentanan keamanan pada jaringan IoT di lingkungan kampus cerdas. Metode IDS berbasis anomali berhasil mendeteksi 98.2% serangan dengan false positive rate 1.8%.',
                'keywords'=> 'IoT, keamanan jaringan, IDS, smart campus, cybersecurity',
                'year'    => 2025,
            ],
            [
                'type'    => 'pengabdian',
                'title'   => 'Pelatihan Literasi Digital untuk Pelaku UMKM di Kecamatan Jatinangor',
                'abstract'=> 'Kegiatan pengabdian kepada masyarakat ini memberikan pelatihan literasi digital kepada 150 pelaku UMKM. Materi meliputi pemasaran digital, penggunaan marketplace, dan pembuatan konten. Hasil evaluasi menunjukkan peningkatan omzet rata-rata 35% pasca pelatihan.',
                'keywords'=> 'literasi digital, UMKM, pemasaran online, pengabdian masyarakat',
                'year'    => 2024,
            ],
            [
                'type'    => 'pengabdian',
                'title'   => 'Pendampingan Implementasi Sistem Keuangan Digital untuk Pondok Pesantren',
                'abstract'=> 'Program pendampingan implementasi sistem pencatatan keuangan digital di 10 pondok pesantren wilayah Sumedang. Sistem yang dikembangkan mencakup modul penerimaan, pengeluaran, laporan keuangan, dan notifikasi otomatis.',
                'keywords'=> 'sistem keuangan, pesantren, digitalisasi, akuntansi',
                'year'    => 2025,
            ],
        ];

        foreach ($penelitianData as $i => $data) {
            $periodYear = $data['year'];
            $period     = PenelitianPeriod::where('type', $data['type'])
                ->where('year', $periodYear)->first();

            $penelitian = Penelitian::firstOrCreate(
                ['title' => $data['title']],
                [
                    'type'         => $data['type'],
                    'period_id'    => $period?->id,
                    'ketua_id'     => $lecturer->id,
                    'study_program_id' => $prodi?->id,
                    'abstract'     => $data['abstract'],
                    'keywords'     => $data['keywords'],
                    'status'       => 'selesai',
                    'is_published' => true,
                    'published_at' => now()->subMonths(rand(1, 18)),
                    'published_by' => $admin->id,
                ]
            );

            // Tambah anggota jika ada lecturer lain
            $otherLecturers = Lecturer::where('id', '!=', $lecturer->id)->take(2)->get();
            foreach ($otherLecturers as $ol) {
                PenelitianMember::firstOrCreate(
                    ['penelitian_id' => $penelitian->id, 'lecturer_id' => $ol->id],
                    ['member_type' => 'dosen']
                );
            }
        }

        // =============================================
        // 3. SKRIPSI (is_published)
        // =============================================
        $skripsiData = [
            [
                'title'    => 'Rancang Bangun Aplikasi E-Commerce Batik Lokal Berbasis Progressive Web App',
                'abstract' => 'Penelitian ini merancang dan membangun aplikasi e-commerce untuk memasarkan produk batik lokal menggunakan teknologi Progressive Web App (PWA). Aplikasi mampu berjalan offline dan memiliki performa loading time di bawah 2 detik.',
                'keywords' => 'PWA, e-commerce, batik, web app',
                'nim'      => '2020001001',
                'name'     => 'Andi Pratama Wijaya',
                'year'     => 2024,
            ],
            [
                'title'    => 'Implementasi Sistem Rekomendasi Buku Perpustakaan Menggunakan Collaborative Filtering',
                'abstract' => 'Skripsi ini mengimplementasikan algoritma collaborative filtering untuk sistem rekomendasi buku perpustakaan digital. Evaluasi menggunakan precision@10 = 0.82 dan recall@10 = 0.75.',
                'keywords' => 'sistem rekomendasi, collaborative filtering, perpustakaan digital',
                'nim'      => '2020001002',
                'name'     => 'Siti Nurhaliza Putri',
                'year'     => 2024,
            ],
            [
                'title'    => 'Pengembangan Dashboard Monitoring Kinerja Dosen Berbasis Data Analytics',
                'abstract' => 'Penelitian mengembangkan dashboard visualisasi data untuk monitoring kinerja dosen meliputi tri dharma perguruan tinggi. Menggunakan Vue.js dan D3.js untuk visualisasi interaktif.',
                'keywords' => 'dashboard, monitoring, kinerja dosen, data analytics, visualisasi',
                'nim'      => '2020001003',
                'name'     => 'Rizky Maulana Hakim',
                'year'     => 2025,
            ],
            [
                'title'    => 'Sistem Deteksi Plagiarisme Dokumen Akademik Menggunakan Algoritma Rabin-Karp',
                'abstract' => 'Skripsi ini membangun sistem deteksi plagiarisme untuk dokumen akademik menggunakan algoritma Rabin-Karp dengan pendekatan shingling. Akurasi deteksi mencapai 91.3% dengan threshold optimal 0.3.',
                'keywords' => 'plagiarisme, Rabin-Karp, shingling, dokumen akademik',
                'nim'      => '2021001004',
                'name'     => 'Dewi Lestari Santoso',
                'year'     => 2025,
            ],
        ];

        foreach ($skripsiData as $data) {
            // Buat student demo jika belum ada
            $student = Student::firstOrCreate(
                ['nim' => $data['nim']],
                [
                    'name'             => $data['name'],
                    'study_program_id' => $prodi?->id,
                    'entry_year'       => substr($data['nim'], 0, 4),
                    'status'           => 'Lulus',
                ]
            );

            $thesis = Thesis::firstOrCreate(
                ['title' => $data['title']],
                [
                    'student_id'       => $student->id,
                    'study_program_id' => $prodi?->id,
                    'abstract'         => $data['abstract'],
                    'keywords'         => $data['keywords'],
                    'status'           => Thesis::STATUS_DIPUBLIKASIKAN,
                    'is_published'     => true,
                    'published_at'     => now()->subMonths(rand(1, 12)),
                    'published_by'     => $admin->id,
                    'completion_date'  => now()->subMonths(rand(2, 14)),
                    'defense_date'     => now()->subMonths(rand(3, 15)),
                ]
            );

            // Tambah pembimbing
            ThesisSupervisor::firstOrCreate(
                ['thesis_id' => $thesis->id, 'lecturer_id' => $lecturer->id],
                ['role' => 'pembimbing_1']
            );
        }

        // =============================================
        // 4. KARYA DOSEN (dipublikasikan)
        // =============================================
        $karyaData = [
            [
                'type'        => 'buku',
                'title'       => 'Pemrograman Web Modern dengan Laravel dan Vue.js',
                'description' => 'Buku panduan komprehensif pengembangan aplikasi web full-stack menggunakan Laravel 11 dan Vue.js 3. Dilengkapi studi kasus sistem informasi akademik.',
                'keywords'    => 'Laravel, Vue.js, web development, full-stack',
                'publisher'   => 'Penerbit Informatika Bandung',
                'isbn_issn'   => '978-602-XXXXX-XX',
                'year'        => 2024,
            ],
            [
                'type'        => 'modul_ajar',
                'title'       => 'Modul Ajar Basis Data Lanjut: NoSQL dan NewSQL',
                'description' => 'Modul pembelajaran mata kuliah Basis Data Lanjut yang mencakup konsep NoSQL (MongoDB, Redis, Cassandra) dan NewSQL. Dilengkapi praktikum dan soal latihan.',
                'keywords'    => 'NoSQL, MongoDB, Redis, basis data, modul ajar',
                'publisher'   => null,
                'isbn_issn'   => null,
                'year'        => 2025,
            ],
            [
                'type'        => 'hki_paten',
                'title'       => 'Sistem dan Metode Deteksi Kecurangan Ujian Online Berbasis Analisis Perilaku',
                'description' => 'HKI untuk sistem deteksi kecurangan ujian online yang menggunakan analisis perilaku keystroke dynamics, eye tracking, dan face recognition untuk mendeteksi aktivitas tidak wajar.',
                'keywords'    => 'HKI, deteksi kecurangan, ujian online, biometrik',
                'publisher'   => null,
                'isbn_issn'   => null,
                'hki_number'  => 'EC00202400001',
                'year'        => 2024,
            ],
            [
                'type'        => 'penelitian_mandiri',
                'title'       => 'Optimasi Algoritma Pencarian Rute Terpendek pada Graf Berbobot Dinamis',
                'description' => 'Penelitian mandiri yang mengusulkan modifikasi algoritma Dijkstra untuk menangani bobot dinamis pada graf real-time. Diaplikasikan pada sistem navigasi kampus.',
                'keywords'    => 'algoritma, Dijkstra, graf dinamis, navigasi, optimasi',
                'publisher'   => null,
                'isbn_issn'   => null,
                'year'        => 2025,
            ],
            [
                'type'        => 'pengabdian_mandiri',
                'title'       => 'Pengembangan Website Desa Wisata Kampung Batik Berbasis CMS',
                'description' => 'Kegiatan pengabdian mandiri membangun website desa wisata untuk Kampung Batik menggunakan CMS WordPress dengan tema kustom. Website menampilkan produk, event, dan booking wisata.',
                'keywords'    => 'website desa, CMS, pariwisata, pengabdian, WordPress',
                'publisher'   => null,
                'isbn_issn'   => null,
                'year'        => 2025,
            ],
        ];

        foreach ($karyaData as $data) {
            LecturerWork::firstOrCreate(
                ['title' => $data['title'], 'lecturer_id' => $lecturer->id],
                [
                    'type'         => $data['type'],
                    'description'  => $data['description'],
                    'keywords'     => $data['keywords'],
                    'publisher'    => $data['publisher'] ?? null,
                    'isbn_issn'    => $data['isbn_issn'] ?? null,
                    'hki_number'   => $data['hki_number'] ?? null,
                    'year'         => $data['year'],
                    'status'       => 'dipublikasikan',
                    'published_at' => now()->subMonths(rand(1, 12)),
                    'published_by' => $admin->id,
                ]
            );
        }

        $this->command->info('✅ Data dummy repository berhasil dibuat:');
        $this->command->info('   - ' . count($penelitianData) . ' penelitian & pengabdian');
        $this->command->info('   - ' . count($skripsiData) . ' skripsi');
        $this->command->info('   - ' . count($karyaData) . ' karya dosen');
    }
}
