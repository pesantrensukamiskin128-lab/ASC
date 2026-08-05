<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Invoice;
use App\Models\Krs;
use App\Models\Lecturer;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudyProgram;
use App\Models\TracerStudy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Ringkasan umum untuk dashboard laporan */
    public function summary(Request $request): JsonResponse
    {
        try {
            $students = Student::count();
            $activeStudents = Student::where('status', 'Aktif')->count();
            $lecturers = Lecturer::where('status', true)->count();

            $alumni = 0;
            try { $alumni = Alumni::count(); } catch (\Exception $e) {}

            $studyPrograms = StudyProgram::where('status', true)->count();

            $totalRevenue = 0;
            try { $totalRevenue = Payment::where('status', 'VERIFIED')->sum('amount') ?? 0; } catch (\Exception $e) {}

            $totalOutstanding = 0;
            try {
                $outstandingResult = Invoice::whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
                    ->selectRaw('COALESCE(SUM(total_amount - discount_amount - scholarship_amount - paid_amount), 0) as total')
                    ->first();
                $totalOutstanding = $outstandingResult->total ?? 0;
            } catch (\Exception $e) {}

            $totalClasses = 0;
            try { $totalClasses = ClassModel::where('is_active', true)->count(); } catch (\Exception $e) {}

            return response()->json([
                'students' => $students,
                'active_students' => $activeStudents,
                'lecturers' => $lecturers,
                'alumni' => $alumni,
                'study_programs' => $studyPrograms,
                'total_revenue' => $totalRevenue,
                'total_outstanding' => max(0, $totalOutstanding),
                'total_classes' => $totalClasses,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /** Statistik mahasiswa */
    public function students(Request $request): JsonResponse
    {
        // Per status
        $byStatus = Student::selectRaw("status, COUNT(*) as count")
            ->groupBy('status')->get();

        // Per program studi
        $byProgram = Student::selectRaw("study_program_id, COUNT(*) as count")
            ->with('studyProgram:id,code,name')
            ->groupBy('study_program_id')->get();

        // Per angkatan
        $byEntryYear = Student::selectRaw("entry_year, COUNT(*) as count")
            ->whereNotNull('entry_year')
            ->groupBy('entry_year')
            ->orderByDesc('entry_year')
            ->limit(10)->get();

        // Per gender
        $byGender = Student::selectRaw("gender, COUNT(*) as count")
            ->whereNotNull('gender')
            ->groupBy('gender')->get();

        // Rasio dosen:mahasiswa per prodi
        $ratios = StudyProgram::withCount(['students as active_students_count' => fn($q) => $q->where('status', 'Aktif')])
            ->withCount('lecturers')
            ->where('status', true)->get()
            ->map(fn($p) => [
                'program' => $p->name,
                'code' => $p->code,
                'students' => $p->active_students_count,
                'lecturers' => $p->lecturers_count,
                'ratio' => $p->lecturers_count > 0 ? round($p->active_students_count / $p->lecturers_count, 1) : 0,
            ]);

        return response()->json([
            'by_status' => $byStatus,
            'by_program' => $byProgram,
            'by_entry_year' => $byEntryYear,
            'by_gender' => $byGender,
            'ratios' => $ratios,
        ]);
    }

    /** Statistik akademik (nilai, IPK, kelulusan) */
    public function academic(Request $request): JsonResponse
    {
        // Distribusi IPK
        $gpaDistribution = StudentGrade::selectRaw("
            CASE
                WHEN grade_point >= 3.5 THEN 'A (3.5-4.0)'
                WHEN grade_point >= 3.0 THEN 'B+ (3.0-3.49)'
                WHEN grade_point >= 2.5 THEN 'B (2.5-2.99)'
                WHEN grade_point >= 2.0 THEN 'C (2.0-2.49)'
                ELSE 'D/E (<2.0)'
            END as grade_range,
            COUNT(*) as count
        ")->groupBy('grade_range')->get();

        // Distribusi huruf mutu
        $gradeDistribution = StudentGrade::selectRaw("letter_grade, COUNT(*) as count")
            ->whereNotNull('letter_grade')
            ->groupBy('letter_grade')
            ->orderByRaw("FIELD(letter_grade, 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'E')")
            ->get();

        // KRS per semester (jumlah pengisi)
        $krsStats = Krs::selectRaw("semester_id, status, COUNT(*) as count")
            ->with('semester:id,name')
            ->groupBy('semester_id', 'status')
            ->orderByDesc('semester_id')
            ->limit(20)->get();

        // Rata-rata IPK per prodi
        $avgGpaByProgram = DB::table('students')
            ->join('student_grades', 'students.id', '=', 'student_grades.student_id')
            ->join('study_programs', 'students.study_program_id', '=', 'study_programs.id')
            ->where('students.status', 'Aktif')
            ->selectRaw('study_programs.code, study_programs.name, AVG(student_grades.grade_point) as avg_gpa')
            ->groupBy('study_programs.id', 'study_programs.code', 'study_programs.name')
            ->get();

        // Tingkat kelulusan per tahun
        $graduationByYear = Alumni::selectRaw("graduation_year, COUNT(*) as count")
            ->groupBy('graduation_year')
            ->orderByDesc('graduation_year')
            ->limit(10)->get();

        return response()->json([
            'gpa_distribution' => $gpaDistribution,
            'grade_distribution' => $gradeDistribution,
            'krs_stats' => $krsStats,
            'avg_gpa_by_program' => $avgGpaByProgram,
            'graduation_by_year' => $graduationByYear,
        ]);
    }

    /** Statistik keuangan */
    public function finance(Request $request): JsonResponse
    {
        // Pendapatan per bulan (12 bulan terakhir)
        $revenueByMonth = Payment::where('status', 'VERIFIED')
            ->where('payment_date', '>=', now()->subMonths(12))
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Status tagihan
        $invoiceByStatus = Invoice::selectRaw("status, COUNT(*) as count, SUM(total_amount) as total_amount")
            ->groupBy('status')->get();

        // Metode pembayaran
        $paymentByMethod = Payment::where('status', 'VERIFIED')
            ->selectRaw("payment_method, COUNT(*) as count, SUM(amount) as total")
            ->groupBy('payment_method')->get();

        // Top tunggakan per prodi
        $outstandingByProgram = DB::table('invoices')
            ->join('students', 'invoices.student_id', '=', 'students.id')
            ->join('study_programs', 'students.study_program_id', '=', 'study_programs.id')
            ->whereIn('invoices.status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
            ->selectRaw("study_programs.code, study_programs.name, COUNT(DISTINCT invoices.student_id) as students, SUM(invoices.total_amount - invoices.discount_amount - invoices.scholarship_amount - invoices.paid_amount) as outstanding")
            ->groupBy('study_programs.id', 'study_programs.code', 'study_programs.name')
            ->orderByDesc('outstanding')
            ->get();

        // Beasiswa
        $scholarshipTotal = Invoice::sum('scholarship_amount');

        return response()->json([
            'revenue_by_month' => $revenueByMonth,
            'invoice_by_status' => $invoiceByStatus,
            'payment_by_method' => $paymentByMethod,
            'outstanding_by_program' => $outstandingByProgram,
            'scholarship_total' => $scholarshipTotal,
        ]);
    }

    /** Statistik SDM (Dosen & Tendik) */
    public function humanResources(Request $request): JsonResponse
    {
        // Dosen per jabatan akademik
        $lecturerByRank = Lecturer::where('status', true)
            ->selectRaw("academic_rank, COUNT(*) as count")
            ->whereNotNull('academic_rank')
            ->groupBy('academic_rank')->get();

        // Dosen per status kepegawaian
        $lecturerByEmployment = Lecturer::where('status', true)
            ->selectRaw("employment_status, COUNT(*) as count")
            ->whereNotNull('employment_status')
            ->groupBy('employment_status')->get();

        // Dosen per prodi
        $lecturerByProgram = Lecturer::where('status', true)
            ->selectRaw("study_program_id, COUNT(*) as count")
            ->with('studyProgram:id,code,name')
            ->groupBy('study_program_id')->get();

        // Dosen per gender
        $lecturerByGender = Lecturer::where('status', true)
            ->selectRaw("gender, COUNT(*) as count")
            ->whereNotNull('gender')
            ->groupBy('gender')->get();

        // Total dosen aktif
        $totalLecturers = Lecturer::where('status', true)->count();
        $totalWithNidn = Lecturer::where('status', true)->whereNotNull('nidn')->where('nidn', '!=', '')->count();

        return response()->json([
            'total_lecturers' => $totalLecturers,
            'total_with_nidn' => $totalWithNidn,
            'by_rank' => $lecturerByRank,
            'by_employment' => $lecturerByEmployment,
            'by_program' => $lecturerByProgram,
            'by_gender' => $lecturerByGender,
        ]);
    }
}
