<?php

namespace App\Http\Controllers\Api;

use App\Exports\TranscriptExport;
use App\Helpers\QrCodeHelper;
use App\Http\Controllers\Controller;
use App\Imports\GradeClassImport;
use App\Imports\GradeImport;
use App\Models\ClassModel;
use App\Models\GradeSchema;
use App\Models\Institution;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Support\AcademicDocumentVerification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\Response;

class GradeController extends Controller
{
    /** Daftar nilai per kelas */
    public function index(Request $request): JsonResponse
    {
        $data = StudentGrade::with(['student:id,nim,name', 'course:id,code,name,credits', 'semester:id,name'])
            ->when($request->class_id, fn ($q) => $q->where('class_id', $request->class_id))
            ->when($request->semester_id, fn ($q) => $q->where('semester_id', $request->semester_id))
            ->when($request->course_id, fn ($q) => $q->where('course_id', $request->course_id))
            ->when($request->student_id, fn ($q) => $q->where('student_id', $request->student_id))
            ->orderBy('student_id')
            ->paginate($request->per_page ?? 50);

        return response()->json($data);
    }

    /** Input/update nilai mahasiswa */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'course_id' => 'required|exists:courses,id',
            'class_id' => 'nullable|exists:classes,id',
            'semester_id' => 'required|exists:semesters,id',
            'components' => 'required|array',           // [{name, weight, score}]
            'components.*.name' => 'required|string',
            'components.*.weight' => 'required|numeric|min:0|max:100',
            'components.*.score' => 'required|numeric|min:0|max:100',
        ]);

        // Hitung nilai akhir
        $finalScore = collect($validated['components'])->sum(fn ($c) => ($c['score'] * $c['weight']) / 100);
        $finalScore = round($finalScore, 2);

        // Konversi ke huruf & bobot
        $schema = GradeSchema::where('is_default', true)->first();
        $conversion = $schema?->convertScore($finalScore);

        $grade = StudentGrade::updateOrCreate(
            ['student_id' => $validated['student_id'], 'course_id' => $validated['course_id'], 'semester_id' => $validated['semester_id']],
            [
                'class_id' => $validated['class_id'],
                'components' => $validated['components'],
                'final_score' => $finalScore,
                'letter_grade' => $conversion['letter'] ?? null,
                'grade_point' => $conversion['grade_point'] ?? null,
                'graded_by' => auth()->id(),
                'graded_at' => now(),
            ]
        );

        return response()->json(['message' => 'Nilai berhasil disimpan.', 'data' => $grade]);
    }

    /** Batch input nilai (untuk satu kelas sekaligus) */
    public function batchStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grades' => 'required|array',
            'grades.*.student_id' => 'required|exists:students,id',
            'grades.*.course_id' => 'required|exists:courses,id',
            'grades.*.class_id' => 'nullable|exists:classes,id',
            'grades.*.semester_id' => 'required|exists:semesters,id',
            'grades.*.components' => 'required|array',
        ]);

        $schema = GradeSchema::where('is_default', true)->first();
        $count = 0;

        DB::transaction(function () use ($validated, $schema, &$count) {
            foreach ($validated['grades'] as $g) {
                $finalScore = collect($g['components'])->sum(fn ($c) => ($c['score'] * $c['weight']) / 100);
                $finalScore = round($finalScore, 2);
                $conversion = $schema?->convertScore($finalScore);

                StudentGrade::updateOrCreate(
                    ['student_id' => $g['student_id'], 'course_id' => $g['course_id'], 'semester_id' => $g['semester_id']],
                    [
                        'class_id' => $g['class_id'] ?? null,
                        'components' => $g['components'],
                        'final_score' => $finalScore,
                        'letter_grade' => $conversion['letter'] ?? null,
                        'grade_point' => $conversion['grade_point'] ?? null,
                        'graded_by' => auth()->id(),
                        'graded_at' => now(),
                    ]
                );
                $count++;
            }
        });

        return response()->json(['message' => "{$count} nilai berhasil disimpan."]);
    }

    /** KHS Mahasiswa (per semester) */
    public function khs(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'nullable|exists:students,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);
        $studentId = $this->requestedStudentId($request);

        $grades = StudentGrade::with(['course:id,code,name,credits'])
            ->where('student_id', $studentId)
            ->where('semester_id', $request->semester_id)
            ->get();

        $totalCredits = $grades->sum(fn ($g) => $g->course->credits);
        $totalPoints = $grades->sum(fn ($g) => $g->course->credits * ($g->grade_point ?? 0));
        $calculatedIps = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0;
        $summary = DB::table('student_semester_summaries')
            ->where(['student_id' => $studentId, 'semester_id' => $request->semester_id])
            ->first();

        return response()->json([
            'grades' => $grades,
            'total_credits' => $totalCredits,
            'ips' => $summary?->semester_gpa ?? $calculatedIps,
            'calculated_ips' => $calculatedIps,
            'summary' => $summary,
        ]);
    }

    /** Transkrip (semua semester) */
    public function transcript(Request $request): JsonResponse
    {
        $request->validate(['student_id' => 'nullable|exists:students,id']);
        $studentId = $this->requestedStudentId($request);

        $grades = StudentGrade::with(['course:id,code,name,credits', 'semester:id,name,start_date'])
            ->where('student_id', $studentId)
            ->get()
            ->sortBy(fn ($grade) => $grade->semester?->start_date?->timestamp ?? 0)
            ->values();

        $totalCredits = $grades->sum(fn ($g) => $g->course->credits);
        $totalPoints = $grades->sum(fn ($g) => $g->course->credits * ($g->grade_point ?? 0));
        $calculatedIpk = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0;
        $latestSummary = DB::table('student_semester_summaries as summaries')
            ->join('semesters', 'semesters.id', '=', 'summaries.semester_id')
            ->where('summaries.student_id', $studentId)
            ->orderByDesc('semesters.start_date')
            ->select('summaries.*')
            ->first();

        return response()->json([
            'grades' => $grades,
            'total_credits' => $totalCredits,
            'ipk' => $latestSummary?->cumulative_gpa ?? $calculatedIpk,
            'calculated_ipk' => $calculatedIpk,
            'summary' => $latestSummary,
        ]);
    }

    /** Cetak KHS per semester untuk admin atau mahasiswa pemilik akun. */
    public function khsPdf(Request $request): Response
    {
        $request->validate([
            'student_id' => 'nullable|exists:students,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);
        $studentId = $this->requestedStudentId($request);
        $student = Student::with(['studyProgram.faculty', 'studyProgram.headLecturer', 'advisor'])
            ->findOrFail($studentId);
        $semester = Semester::findOrFail($request->integer('semester_id'));
        $grades = StudentGrade::with('course:id,code,name,credits')
            ->where(['student_id' => $studentId, 'semester_id' => $semester->id])
            ->orderBy('course_id')
            ->get();
        $totalCredits = $grades->sum(fn ($grade) => (int) ($grade->course?->credits ?? 0));
        $totalPoints = $grades->sum(fn ($grade) => (int) ($grade->course?->credits ?? 0) * (float) ($grade->grade_point ?? 0));
        $calculatedIps = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0;
        $summary = DB::table('student_semester_summaries')
            ->where(['student_id' => $studentId, 'semester_id' => $semester->id])
            ->first();
        $qrData = $this->academicDocumentQrData('khs', $student, $grades, $semester->id);

        $pdf = Pdf::loadView('pdf.khs', $this->pdfData() + $qrData + [
            'student' => $student,
            'semester' => $semester,
            'grades' => $grades,
            'summary' => $summary,
            'totalCredits' => $totalCredits,
            'ips' => $summary?->semester_gpa ?? $calculatedIps,
        ])->setPaper('a4', 'portrait')->setOption('isRemoteEnabled', true);

        return $pdf->download('KHS-'.$student->nim.'-'.$this->safeFilename($semester->name).'.pdf');
    }

    /** Cetak transkrip nilai lengkap untuk admin atau mahasiswa pemilik akun. */
    public function transcriptPdf(Request $request): Response
    {
        $request->validate(['student_id' => 'nullable|exists:students,id']);
        $studentId = $this->requestedStudentId($request);
        $student = Student::with(['studyProgram.faculty', 'studyProgram.headLecturer', 'advisor'])
            ->findOrFail($studentId);
        $grades = StudentGrade::with(['course:id,code,name,credits', 'semester:id,name,start_date'])
            ->where('student_id', $studentId)
            ->get()
            ->sortBy(fn ($grade) => [
                $grade->semester?->start_date?->timestamp ?? 0,
                $grade->course?->code ?? '',
            ])->values();
        $totalCredits = $grades->sum(fn ($grade) => (int) ($grade->course?->credits ?? 0));
        $totalPoints = $grades->sum(fn ($grade) => (int) ($grade->course?->credits ?? 0) * (float) ($grade->grade_point ?? 0));
        $calculatedIpk = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0;
        $latestSummary = DB::table('student_semester_summaries as summaries')
            ->join('semesters', 'semesters.id', '=', 'summaries.semester_id')
            ->where('summaries.student_id', $studentId)
            ->orderByDesc('semesters.start_date')
            ->select('summaries.*')
            ->first();
        $qrData = $this->academicDocumentQrData('transcript', $student, $grades);

        $pdf = Pdf::loadView('pdf.transcript', $this->pdfData() + $qrData + [
            'student' => $student,
            'grades' => $grades,
            'totalCredits' => $totalCredits,
            'ipk' => $latestSummary?->cumulative_gpa ?? $calculatedIpk,
        ])->setPaper('a4', 'portrait')->setOption('isRemoteEnabled', true);

        return $pdf->download('Transkrip-'.$student->nim.'.pdf');
    }

    /** Download transkrip Excel khusus pengelola akademik. */
    public function transcriptExcel(Request $request): Response
    {
        $request->validate(['student_id' => 'required|exists:students,id']);
        $studentId = $this->requestedStudentId($request);
        $student = Student::with(['studyProgram.faculty'])->findOrFail($studentId);
        $grades = StudentGrade::with(['course:id,code,name,credits', 'semester:id,name,start_date'])
            ->where('student_id', $studentId)
            ->get()
            ->sortBy(fn ($grade) => [
                $grade->semester?->start_date?->timestamp ?? 0,
                $grade->course?->code ?? '',
            ])->values();
        $totalCredits = $grades->sum(fn ($grade) => (int) ($grade->course?->credits ?? 0));
        $totalPoints = $grades->sum(fn ($grade) => (int) ($grade->course?->credits ?? 0) * (float) ($grade->grade_point ?? 0));
        $calculatedIpk = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0;
        $latestSummary = DB::table('student_semester_summaries as summaries')
            ->join('semesters', 'semesters.id', '=', 'summaries.semester_id')
            ->where('summaries.student_id', $studentId)
            ->orderByDesc('semesters.start_date')
            ->select('summaries.cumulative_gpa')
            ->first();

        return Excel::download(
            new TranscriptExport($student, $grades, Institution::first(), (float) ($latestSummary?->cumulative_gpa ?? $calculatedIpk)),
            'Transkrip-'.$student->nim.'.xlsx'
        );
    }

    /**
     * Mahasiswa hanya boleh membaca nilainya sendiri. Pengguna akademik tetap
     * dapat memilih mahasiswa melalui student_id pada endpoint yang sama.
     */
    private function requestedStudentId(Request $request): int
    {
        $user = $request->user();
        if ($user?->hasRole('MAHASISWA')) {
            $student = $user->student;
            abort_if(! $student, 404, 'Akun ini belum terhubung dengan data mahasiswa.');

            return (int) $student->id;
        }

        $studentId = $request->integer('student_id');
        abort_if(! $studentId || ! Student::whereKey($studentId)->exists(), 422, 'Mahasiswa wajib dipilih.');

        return $studentId;
    }

    /** @return array{institution:?Institution,logoPath:?string,letterheadPath:?string,printedAt:Carbon} */
    private function pdfData(): array
    {
        $institution = Institution::first();
        $logoPath = $institution?->logo_path ? storage_path('app/public/'.$institution->logo_path) : null;
        $letterheadPath = $institution?->letterhead_path ? storage_path('app/public/'.$institution->letterhead_path) : null;

        return compact('institution', 'logoPath', 'letterheadPath') + ['printedAt' => now()];
    }

    private function safeFilename(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? '', '-');
    }

    /** @return array{verifyUrl:string,qrSignature:string,qrVerification:string} */
    private function academicDocumentQrData(string $type, Student $student, Collection $grades, ?int $semesterId = null): array
    {
        $token = AcademicDocumentVerification::issue($type, $student, $grades, $semesterId);
        $verifyUrl = rtrim((string) config('app.frontend_url'), '/').'/verify/'.$type.'/'.$token;

        return [
            'verifyUrl' => $verifyUrl,
            'qrSignature' => QrCodeHelper::generate($verifyUrl.'?signer=kaprodi', 240),
            'qrVerification' => QrCodeHelper::generateWithLogo($verifyUrl, 240),
        ];
    }

    /** Skema nilai aktif */
    public function schema(): JsonResponse
    {
        return response()->json(GradeSchema::with('details')->where('is_default', true)->first());
    }

    /** Import nilai dari Excel (untuk migrasi data eksisting) */
    public function importGrades(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        $import = new GradeImport;
        Excel::import($import, $request->file('file'));

        $errors = collect($import->errors())->map(fn ($e) => $e->getMessage())->values();

        return response()->json([
            'message' => "Import selesai. {$import->getImportedCount()} nilai baru diimport.",
            'imported' => $import->getImportedCount(),
            'errors' => $errors,
        ]);
    }

    /** Import nilai per kelas dari Excel (digunakan dosen di InputNilai) */
    public function importClassGrades(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'class_id' => 'required|exists:classes,id',
            'semester_id' => 'required|exists:semesters,id',
            'components' => 'required|json',
        ]);

        $class = ClassModel::findOrFail($request->class_id);
        $components = json_decode($request->components, true);

        $import = new GradeClassImport(
            (int) $request->class_id,
            (int) $class->course_id,
            (int) $request->semester_id,
            $components
        );
        Excel::import($import, $request->file('file'));

        return response()->json([
            'message' => "{$import->imported} nilai berhasil diimport.".($import->skipped > 0 ? " {$import->skipped} baris dilewati." : ''),
            'imported' => $import->imported,
            'skipped' => $import->skipped,
            'errors' => $import->errors,
        ]);
    }

    /** Download template Excel untuk import nilai per kelas */
    public function downloadClassTemplate(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'semester_id' => 'required|exists:semesters,id',
            'components' => 'required|json',
        ]);

        $class = ClassModel::with(['course', 'members.student'])->findOrFail($request->class_id);
        $components = json_decode($request->components, true);

        // Header: nim, nama, lalu satu kolom per komponen
        $header = ['nim', 'nama'];
        foreach ($components as $comp) {
            $header[] = strtolower(str_replace([' ', '-'], '_', $comp['name']));
        }

        // Isi: daftar mahasiswa di kelas
        $rows = [];
        foreach ($class->members as $member) {
            $row = [
                $member->student?->nim ?? '',
                $member->student?->name ?? '',
            ];
            foreach ($components as $comp) {
                $row[] = 0; // Default skor 0
            }
            $rows[] = $row;
        }

        $filename = "template-nilai-{$class->name}.xlsx";
        $headerData = $header;
        $componentHeaders = $components;

        return Excel::download(
            new class($headerData, $rows, $componentHeaders) implements FromArray, WithColumnWidths, WithStyles
            {
                public function __construct(
                    private array $header,
                    private array $rows,
                    private array $components
                ) {}

                public function array(): array
                {
                    return array_merge([$this->header], $this->rows);
                }

                public function styles(Worksheet $sheet)
                {
                    $lastCol = Coordinate::stringFromColumnIndex(count($this->header));
                    // Header styling
                    $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2563EB']],
                        'alignment' => ['horizontal' => 'center'],
                    ]);
                    // Freeze header row
                    $sheet->freezePane('A2');
                    // Note di header untuk komponen
                    $colIdx = 3;
                    foreach ($this->components as $comp) {
                        $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                        $sheet->getComment("{$colLetter}1")->getText()->createTextRun("Bobot: {$comp['weight']}% | Nilai 0-100");
                        $colIdx++;
                    }

                    return [];
                }

                public function columnWidths(): array
                {
                    $widths = ['A' => 15, 'B' => 30];
                    $idx = 3;
                    foreach ($this->components as $comp) {
                        $col = Coordinate::stringFromColumnIndex($idx);
                        $widths[$col] = 12;
                        $idx++;
                    }

                    return $widths;
                }
            },
            $filename
        );
    }
}
