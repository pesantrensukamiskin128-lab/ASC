<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendar;
use App\Models\AcademicYear;
use App\Models\Alumni;
use App\Models\ClassModel;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Invoice;
use App\Models\Krs;
use App\Models\Lecturer;
use App\Models\LecturerPosition;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudyProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Dashboard utama — return data sesuai role user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user->getRoleNames()->toArray();

        // Determine primary role
        if (in_array('SUPER_ADMIN', $roles) || in_array('ADMIN_AKADEMIK', $roles)) {
            return $this->adminDashboard();
        }

        if (in_array('DOSEN', $roles)) {
            return $this->dosenDashboard($user);
        }

        if (in_array('MAHASISWA', $roles)) {
            return $this->mahasiswaDashboard($user);
        }

        if (in_array('KEUANGAN', $roles)) {
            return $this->keuanganDashboard();
        }

        // Fallback
        return $this->adminDashboard();
    }

    /**
     * Dashboard Admin/Pimpinan — overview seluruh institusi
     */
    private function adminDashboard(): JsonResponse
    {
        $activeSemester = Semester::where('is_active', true)->first();

        $data = [
            'role' => 'ADMIN',
            'semester' => $activeSemester?->name ?? '-',
            'stats' => [
                'total_students' => Student::count(),
                'active_students' => Student::where('status', 'Aktif')->count(),
                'total_lecturers' => Lecturer::where('status', true)->count(),
                'total_prodi' => StudyProgram::where('status', true)->count(),
                'total_alumni' => Alumni::count(),
                'active_classes' => ClassModel::where('is_active', true)->count(),
            ],
            'finance' => [
                'total_revenue' => Payment::where('status', 'VERIFIED')->sum('amount') ?? 0,
                'total_outstanding' => 0,
                'pending_payments' => Payment::where('status', 'PENDING')->count(),
            ],
            'students_by_status' => Student::selectRaw("status, COUNT(*) as count")->groupBy('status')->get(),
            'students_by_prodi' => Student::selectRaw("study_program_id, COUNT(*) as count")
                ->where('status', 'Aktif')
                ->with('studyProgram:id,code,name')
                ->groupBy('study_program_id')->get(),
            'students_by_entry_year' => Student::selectRaw("entry_year, COUNT(*) as count")
                ->where('status', 'Aktif')
                ->whereNotNull('entry_year')
                ->groupBy('entry_year')
                ->orderByDesc('entry_year')
                ->limit(8)->get(),
            'recent_activities' => [],
        ];

        // Safe outstanding calc
        try {
            $data['finance']['total_outstanding'] = Invoice::whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
                ->selectRaw('COALESCE(SUM(total_amount - discount_amount - scholarship_amount - paid_amount), 0) as total')
                ->value('total') ?? 0;
        } catch (\Exception $e) {}

        return response()->json($data);
    }

    /**
     * Dashboard Dosen — kelas, mahasiswa bimbingan, jadwal + info jabatan
     */
    private function dosenDashboard($user): JsonResponse
    {
        $lecturer = Lecturer::where('user_id', $user->id)->first();
        $lecturerId = $lecturer?->id;
        $activeSemester = Semester::where('is_active', true)->first();

        $data = [
            'role' => 'DOSEN',
            'semester' => $activeSemester?->name ?? '-',
            'lecturer_name' => $lecturer?->display_name ?? $user->name,
            'stats' => [
                'my_classes' => $lecturerId ? ClassModel::where('lecturer_id', $lecturerId)->where('is_active', true)->count() : 0,
                'my_advisees' => $lecturerId ? Student::where('advisor_id', $lecturerId)->where('status', 'Aktif')->count() : 0,
                'pending_krs' => $lecturerId ? Krs::where('advisor_id', $lecturerId)->where('status', 'SUBMITTED')->count() : 0,
                'total_students_in_class' => 0,
            ],
            'positions' => [],
            'position_dashboard' => null,
            'my_classes' => [],
            'pending_approvals' => [],
            'upcoming_calendar' => [],
        ];

        // Kalender akademik (selalu tampil)
        $data['upcoming_calendar'] = AcademicCalendar::where('end_date', '>=', now())
            ->orderBy('start_date')
            ->limit(5)->get()
            ->map(fn($c) => [
                'title' => $c->title,
                'start_date' => $c->start_date?->format('Y-m-d'),
                'end_date' => $c->end_date?->format('Y-m-d'),
                'category' => $c->category,
                'color' => $c->color,
            ]);

        if (!$lecturerId) return response()->json($data);

        // Jabatan aktif
        $activePositions = LecturerPosition::where('lecturer_id', $lecturerId)
            ->where('is_active', true)->get();

        $data['positions'] = $activePositions->map(fn($p) => [
            'code' => $p->position_code,
            'name' => $p->position_name,
            'scope_type' => $p->scope_type,
            'scope_id' => $p->scope_id,
        ]);

        // Dashboard tambahan berdasarkan jabatan
        foreach ($activePositions as $pos) {
            if (in_array($pos->position_code, ['KAPRODI', 'SEKPRODI'])) {
                $data['position_dashboard'] = $this->kaprodiSection($pos);
                break;
            }
            if (in_array($pos->position_code, ['DEKAN', 'WADEK1', 'WADEK2', 'WADEK3'])) {
                $data['position_dashboard'] = $this->dekanSection($pos);
                break;
            }
            if (in_array($pos->position_code, ['KETUA', 'REKTOR', 'WK1', 'WR1', 'WK2', 'WR2', 'WK3', 'WR3'])) {
                // Pimpinan PT → tampilkan data seluruh institusi ringkas
                $data['position_dashboard'] = $this->pimpinanSection();
                break;
            }
        }

        // Kelas yang diajar
        $data['my_classes'] = ClassModel::where('lecturer_id', $lecturerId)
            ->where('is_active', true)
            ->with('course:id,code,name,credits')
            ->withCount('members')
            ->limit(10)->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'course_code' => $c->course?->code,
                'course_name' => $c->course?->name,
                'credits' => $c->course?->credits,
                'class_name' => $c->name,
                'students' => $c->members_count,
                'capacity' => $c->capacity,
            ]);

        $data['stats']['total_students_in_class'] = ClassModel::where('lecturer_id', $lecturerId)
            ->where('is_active', true)->withCount('members')->get()->sum('members_count');

        // KRS pending
        $data['pending_approvals'] = Krs::where('advisor_id', $lecturerId)
            ->where('status', 'SUBMITTED')
            ->with('student:id,nim,name')
            ->limit(5)->get()
            ->map(fn($k) => [
                'id' => $k->id,
                'student_nim' => $k->student?->nim,
                'student_name' => $k->student?->name,
                'total_credits' => $k->total_credits,
                'submitted_at' => $k->submitted_at,
            ]);

        // Agenda kegiatan yang diundang (mendatang)
        $data['upcoming_events'] = Event::whereHas('invitees', fn($q) => $q->where('user_id', $user->id))
            ->where('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->limit(5)->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'event_date' => $e->event_date?->format('Y-m-d'),
                'start_time' => $e->start_time,
                'end_time' => $e->end_time,
                'location' => $e->location,
                'category' => $e->category,
                'organizer' => $e->organizer,
            ]);

        // Riwayat kehadiran agenda
        $data['event_attendance_history'] = EventAttendance::where('user_id', $user->id)
            ->with('event:id,title,event_date,location,category')
            ->orderByDesc('attended_at')
            ->limit(10)->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'event_id' => $a->event_id,
                'event_title' => $a->event?->title,
                'event_date' => $a->event?->event_date?->format('Y-m-d'),
                'location' => $a->event?->location,
                'category' => $a->event?->category,
                'attended_at' => $a->attended_at?->format('Y-m-d H:i'),
                'method' => $a->method,
            ]);

        return response()->json($data);
    }

    /**
     * Section tambahan: Kaprodi/Sekprodi — kondisi program studi
     */
    private function kaprodiSection($position): array
    {
        $prodiId = $position->scope_id;
        $prodi = StudyProgram::find($prodiId);

        if (!$prodi) return ['type' => 'KAPRODI', 'error' => 'Program studi tidak ditemukan.'];

        $activeStudents = Student::where('study_program_id', $prodiId)->where('status', 'Aktif')->count();
        $cutiStudents = Student::where('study_program_id', $prodiId)->where('status', 'Cuti')->count();
        $totalLecturers = Lecturer::where('study_program_id', $prodiId)->where('status', true)->count();
        $totalClasses = ClassModel::where('study_program_id', $prodiId)->where('is_active', true)->count();

        // Mahasiswa per angkatan
        $byEntryYear = Student::where('study_program_id', $prodiId)
            ->where('status', 'Aktif')
            ->selectRaw("entry_year, COUNT(*) as count")
            ->whereNotNull('entry_year')
            ->groupBy('entry_year')
            ->orderByDesc('entry_year')
            ->limit(6)->get();

        // KRS belum submit di prodi ini
        $pendingKrs = Krs::whereHas('student', fn($q) => $q->where('study_program_id', $prodiId))
            ->where('status', 'SUBMITTED')->count();

        // Rasio dosen:mahasiswa
        $ratio = $totalLecturers > 0 ? round($activeStudents / $totalLecturers, 1) : 0;

        return [
            'type' => 'KAPRODI',
            'position_name' => $position->position_name,
            'prodi' => ['id' => $prodi->id, 'code' => $prodi->code, 'name' => $prodi->name],
            'stats' => [
                'active_students' => $activeStudents,
                'cuti_students' => $cutiStudents,
                'lecturers' => $totalLecturers,
                'classes' => $totalClasses,
                'pending_krs' => $pendingKrs,
                'ratio' => $ratio,
            ],
            'students_by_entry_year' => $byEntryYear,
        ];
    }

    /**
     * Section tambahan: Dekan — kondisi fakultas
     */
    private function dekanSection($position): array
    {
        $facultyId = $position->scope_id;

        $prodiIds = StudyProgram::where('faculty_id', $facultyId)->pluck('id');
        $totalStudents = Student::whereIn('study_program_id', $prodiIds)->where('status', 'Aktif')->count();
        $totalLecturers = Lecturer::whereIn('study_program_id', $prodiIds)->where('status', true)->count();
        $totalProdi = $prodiIds->count();

        $byProdi = Student::whereIn('study_program_id', $prodiIds)
            ->where('status', 'Aktif')
            ->selectRaw("study_program_id, COUNT(*) as count")
            ->with('studyProgram:id,code,name')
            ->groupBy('study_program_id')->get();

        return [
            'type' => 'DEKAN',
            'position_name' => $position->position_name,
            'stats' => [
                'total_students' => $totalStudents,
                'total_lecturers' => $totalLecturers,
                'total_prodi' => $totalProdi,
            ],
            'students_by_prodi' => $byProdi,
        ];
    }

    /**
     * Section tambahan: Pimpinan PT — ringkasan seluruh institusi
     */
    private function pimpinanSection(): array
    {
        return [
            'type' => 'PIMPINAN',
            'stats' => [
                'total_students' => Student::where('status', 'Aktif')->count(),
                'total_lecturers' => Lecturer::where('status', true)->count(),
                'total_prodi' => StudyProgram::where('status', true)->count(),
                'total_alumni' => Alumni::count(),
                'revenue' => Payment::where('status', 'VERIFIED')->sum('amount') ?? 0,
            ],
        ];
    }

    /**
     * Dashboard Mahasiswa — IPK, KRS, tagihan, jadwal
     */
    private function mahasiswaDashboard($user): JsonResponse
    {
        $student = Student::where('user_id', $user->id)->first();
        $activeSemester = Semester::where('is_active', true)->first();

        if (!$student) {
            return response()->json(['role' => 'MAHASISWA', 'error' => 'Data mahasiswa tidak ditemukan.']);
        }

        // Hitung IPK
        $grades = StudentGrade::where('student_id', $student->id)
            ->whereNotNull('grade_point')
            ->join('courses', 'student_grades.course_id', '=', 'courses.id')
            ->selectRaw('SUM(courses.credits * student_grades.grade_point) as total_points, SUM(courses.credits) as total_credits')
            ->first();

        $ipk = ($grades->total_credits > 0) ? round($grades->total_points / $grades->total_credits, 2) : 0;
        $totalCredits = $grades->total_credits ?? 0;

        // IPS semester aktif
        $ips = 0;
        if ($activeSemester) {
            $semGrades = StudentGrade::where('student_id', $student->id)
                ->where('semester_id', $activeSemester->id)
                ->whereNotNull('grade_point')
                ->join('courses', 'student_grades.course_id', '=', 'courses.id')
                ->selectRaw('SUM(courses.credits * student_grades.grade_point) as total_points, SUM(courses.credits) as total_credits')
                ->first();
            $ips = ($semGrades->total_credits > 0) ? round($semGrades->total_points / $semGrades->total_credits, 2) : 0;
        }

        // KRS aktif
        $activeKrs = $activeSemester ? Krs::where('student_id', $student->id)
            ->where('semester_id', $activeSemester->id)->first() : null;

        // Tagihan
        $unpaidInvoices = Invoice::where('student_id', $student->id)
            ->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])->get();
        $totalUnpaid = $unpaidInvoices->sum(fn($i) => $i->total_amount - $i->discount_amount - $i->scholarship_amount - $i->paid_amount);

        $data = [
            'role' => 'MAHASISWA',
            'semester' => $activeSemester?->name ?? '-',
            'student' => [
                'nim' => $student->nim,
                'name' => $student->name,
                'prodi' => $student->studyProgram?->name,
                'entry_year' => $student->entry_year,
                'current_semester' => $student->current_semester,
                'status' => $student->status,
                'advisor' => $student->advisor?->full_name ?? $student->advisor?->display_name ?? '-',
            ],
            'academic' => [
                'ipk' => $ipk,
                'ips' => $ips,
                'total_credits' => (int) $totalCredits,
                'krs_status' => $activeKrs?->status ?? 'BELUM_ISI',
                'krs_credits' => $activeKrs?->total_credits ?? 0,
            ],
            'finance' => [
                'total_unpaid' => max(0, $totalUnpaid),
                'unpaid_count' => $unpaidInvoices->count(),
            ],
            'quick_links' => $this->studentQuickLinks($activeKrs),
            'upcoming_calendar' => AcademicCalendar::where('end_date', '>=', now())
                ->orderBy('start_date')
                ->limit(5)->get()
                ->map(fn($c) => [
                    'title' => $c->title,
                    'start_date' => $c->start_date?->format('Y-m-d'),
                    'end_date' => $c->end_date?->format('Y-m-d'),
                    'category' => $c->category,
                    'color' => $c->color,
                ]),
            'upcoming_events' => Event::whereHas('invitees', fn($q) => $q->where('user_id', $user->id))
                ->where('event_date', '>=', now()->toDateString())
                ->orderBy('event_date')
                ->limit(5)->get()
                ->map(fn($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'event_date' => $e->event_date?->format('Y-m-d'),
                    'start_time' => $e->start_time,
                    'end_time' => $e->end_time,
                    'location' => $e->location,
                    'category' => $e->category,
                    'organizer' => $e->organizer,
                ]),
            'event_attendance_history' => EventAttendance::where('user_id', $user->id)
                ->with('event:id,title,event_date,location,category')
                ->orderByDesc('attended_at')
                ->limit(10)->get()
                ->map(fn($a) => [
                    'id' => $a->id,
                    'event_id' => $a->event_id,
                    'event_title' => $a->event?->title,
                    'event_date' => $a->event?->event_date?->format('Y-m-d'),
                    'location' => $a->event?->location,
                    'category' => $a->event?->category,
                    'attended_at' => $a->attended_at?->format('Y-m-d H:i'),
                    'method' => $a->method,
                ]),
        ];

        return response()->json($data);
    }

    private function studentQuickLinks($krs): array
    {
        $links = [];
        if (!$krs || $krs->status === 'BELUM_ISI') {
            $links[] = ['label' => 'Isi KRS', 'to' => '/akademik/krs-saya', 'color' => 'blue'];
        }
        $links[] = ['label' => 'Lihat KHS', 'to' => '/akademik/krs-saya', 'color' => 'green'];
        $links[] = ['label' => 'Jadwal Kuliah', 'to' => '/akademik/kelas', 'color' => 'purple'];
        $links[] = ['label' => 'Tagihan', 'to' => '/keuangan/saya', 'color' => 'orange'];
        return $links;
    }

    /**
     * Dashboard Keuangan — fokus keuangan
     */
    private function keuanganDashboard(): JsonResponse
    {
        $activeSemester = Semester::where('is_active', true)->first();

        $totalInvoiced = Invoice::whereNotIn('status', ['CANCELLED', 'WAIVED'])->sum('total_amount');
        $totalPaid = Payment::where('status', 'VERIFIED')->sum('amount');
        $pendingPayments = Payment::where('status', 'PENDING')->count();
        $overdueInvoices = Invoice::where('status', 'OVERDUE')->count();

        return response()->json([
            'role' => 'KEUANGAN',
            'semester' => $activeSemester?->name ?? '-',
            'stats' => [
                'total_invoiced' => $totalInvoiced,
                'total_paid' => $totalPaid,
                'total_outstanding' => max(0, $totalInvoiced - $totalPaid),
                'pending_payments' => $pendingPayments,
                'overdue_invoices' => $overdueInvoices,
            ],
            'recent_payments' => Payment::with('student:id,nim,name')
                ->where('status', 'PENDING')
                ->orderByDesc('created_at')
                ->limit(5)->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'student_name' => $p->student?->name,
                    'student_nim' => $p->student?->nim,
                    'amount' => $p->amount,
                    'method' => $p->payment_method,
                    'date' => $p->payment_date,
                ]),
        ]);
    }
}
