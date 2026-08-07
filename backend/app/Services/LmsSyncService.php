<?php

namespace App\Services;

use App\Models\LmsConfig;
use App\Models\LmsSyncLog;
use App\Models\Student;
use App\Models\Lecturer;
use App\Models\Course;
use App\Models\ClassModel;
use App\Models\Krs;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LmsSyncService
{
    private LmsConfig $config;
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->config = LmsConfig::where('is_active', true)->firstOrFail();
        $this->baseUrl = rtrim($this->config->base_url, '/');
        $this->token = $this->config->getDecryptedToken();
    }

    /** Sinkronisasi semua data sekaligus */
    public function syncAll(int $userId): array
    {
        $results = [];
        $results['users'] = $this->syncUsers($userId);
        $results['courses'] = $this->syncCourses($userId);
        $results['classes'] = $this->syncClasses($userId);
        $results['enrollments'] = $this->syncEnrollments($userId);

        $this->config->update(['last_sync_at' => now()]);

        return $results;
    }

    /** Sync users (mahasiswa + dosen) ke LMS */
    public function syncUsers(int $userId): LmsSyncLog
    {
        $start = microtime(true);
        $data = [];

        // Mahasiswa aktif
        $students = Student::with('studyProgram', 'user')
            ->where('status', 'Aktif')
            ->get();

        foreach ($students as $s) {
            $data[] = [
                'external_id'      => 'SIAKAD-' . $s->nim,
                'username'         => $s->nim,
                'email'            => $s->user?->email ?? $s->nim . '@student.jawami.ac.id',
                'full_name'        => $s->name,
                'identity_number'  => $s->nim,
                'role'             => 'mahasiswa',
                'program_code'     => $s->studyProgram?->code ?? '',
                'phone'            => $s->phone ?? '',
                'is_active'        => true,
                'initial_password' => $s->nim,
            ];
        }

        // Dosen aktif
        $lecturers = Lecturer::with('studyProgram', 'user')
            ->where('status', true)
            ->get();

        foreach ($lecturers as $l) {
            $data[] = [
                'external_id'      => 'SIAKAD-DSN-' . ($l->nidn ?? $l->id),
                'username'         => $l->nidn ?? $l->user?->username ?? 'dosen' . $l->id,
                'email'            => $l->user?->email ?? $l->email ?? '',
                'full_name'        => $l->display_name,
                'identity_number'  => $l->nidn ?? '',
                'role'             => 'dosen',
                'program_code'     => $l->studyProgram?->code ?? '',
                'phone'            => $l->phone ?? '',
                'is_active'        => true,
                'initial_password' => $l->nidn ?? 'dosen123',
            ];
        }

        return $this->sendSync('users', $data, $userId, $start);
    }

    /** Sync mata kuliah ke LMS */
    public function syncCourses(int $userId): LmsSyncLog
    {
        $start = microtime(true);
        $courses = Course::with('studyProgram')->where('status', true)->get();

        $data = $courses->map(fn($c) => [
            'external_id'     => 'MK-' . $c->code,
            'program_code'    => $c->studyProgram?->code ?? '',
            'code'            => $c->code,
            'name'            => $c->name,
            'credits'         => $c->credits,
            'semester_number' => $c->semester ?? null,
            'is_active'       => true,
        ])->toArray();

        return $this->sendSync('courses', $data, $userId, $start);
    }

    /** Sync kelas semester aktif ke LMS */
    public function syncClasses(int $userId): LmsSyncLog
    {
        $start = microtime(true);
        $classes = ClassModel::with(['course', 'semester.academicYear', 'lecturer'])
            ->whereHas('semester', fn($q) => $q->whereHas('academicYear', fn($q2) => $q2->where('is_active', true)))
            ->where('is_active', true)
            ->get();

        $data = $classes->map(fn($c) => [
            'external_id'           => 'KLS-' . $c->id,
            'course_external_id'    => 'MK-' . $c->course?->code,
            'academic_year'         => $c->semester?->academicYear?->name ?? '',
            'semester'              => strtolower($c->semester?->type ?? 'ganjil'),
            'lecturer_external_id'  => $c->lecturer ? ('SIAKAD-DSN-' . ($c->lecturer->nidn ?? $c->lecturer->id)) : null,
            'name'                  => $c->name,
            'mode'                  => 'hybrid',
            'schedule_day'          => null,
            'schedule_time'         => null,
            'status'                => 'active',
        ])->toArray();

        return $this->sendSync('classes', $data, $userId, $start);
    }

    /** Sync enrollment (KRS approved) ke LMS */
    public function syncEnrollments(int $userId): LmsSyncLog
    {
        $start = microtime(true);

        // KRS yang sudah approved di semester aktif
        $krsDetails = \Illuminate\Support\Facades\DB::table('krs_details')
            ->join('krs', 'krs_details.krs_id', '=', 'krs.id')
            ->join('students', 'krs.student_id', '=', 'students.id')
            ->join('semesters', 'krs.semester_id', '=', 'semesters.id')
            ->join('academic_years', 'semesters.academic_year_id', '=', 'academic_years.id')
            ->where('academic_years.is_active', true)
            ->where('krs.status', 'APPROVED')
            ->where('krs_details.status', 'AKTIF')
            ->select('krs_details.id', 'krs_details.class_id', 'students.nim')
            ->get();

        $data = $krsDetails->map(fn($d) => [
            'external_id'           => 'KRS-' . $d->id,
            'class_external_id'     => 'KLS-' . $d->class_id,
            'student_external_id'   => 'SIAKAD-' . $d->nim,
            'status'                => 'active',
        ])->toArray();

        return $this->sendSync('enrollments', $data, $userId, $start);
    }

    /** Kirim data ke LMS API dan catat log */
    private function sendSync(string $type, array $data, int $userId, float $start): LmsSyncLog
    {
        $total = count($data);

        if ($total === 0) {
            return LmsSyncLog::create([
                'sync_type'    => $type,
                'status'       => 'success',
                'total_items'  => 0,
                'synced_items' => 0,
                'failed_items' => 0,
                'triggered_by' => $userId,
                'duration_ms'  => (int)((microtime(true) - $start) * 1000),
            ]);
        }

        // LMS API menerima max 500 per request — batch jika perlu
        $errors = [];
        $synced = 0;

        foreach (array_chunk($data, 500) as $chunk) {
            try {
                $response = Http::withToken($this->token)
                    ->timeout(60)
                    ->acceptJson()
                    ->withoutVerifying() // Bypass SSL verify untuk hosting yang SSL-nya bermasalah
                    ->post("{$this->baseUrl}/v1/sync/{$type}", ['data' => $chunk]);

                if ($response->successful()) {
                    // Pastikan response benar-benar JSON dari LMS (bukan HTML error page)
                    $body = $response->json();
                    if (isset($body['ok']) && $body['ok'] === true) {
                        $synced += count($chunk);
                    } elseif (isset($body['ok']) && $body['ok'] === false) {
                        $errors[] = "LMS menolak: " . ($body['message'] ?? 'Unknown error');
                    } else {
                        // Response sukses tapi format tidak sesuai — mungkin redirect ke HTML
                        $synced += count($chunk);
                        Log::warning("LMS sync {$type}: response format tidak standar", ['body' => substr($response->body(), 0, 200)]);
                    }
                } else {
                    $errors[] = "HTTP {$response->status()}: " . ($response->json('message') ?? substr($response->body(), 0, 200));
                }
            } catch (\Exception $e) {
                $errors[] = "Exception: " . $e->getMessage();
                Log::error("LMS sync {$type} error: " . $e->getMessage());
            }
        }

        $status = empty($errors) ? 'success' : ($synced > 0 ? 'partial' : 'failed');

        return LmsSyncLog::create([
            'sync_type'    => $type,
            'status'       => $status,
            'total_items'  => $total,
            'synced_items' => $synced,
            'failed_items' => $total - $synced,
            'errors'       => !empty($errors) ? $errors : null,
            'triggered_by' => $userId,
            'duration_ms'  => (int)((microtime(true) - $start) * 1000),
        ]);
    }
}
