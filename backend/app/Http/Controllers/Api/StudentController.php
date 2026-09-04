<?php

namespace App\Http\Controllers\Api;

use App\Exports\StudentExport;
use App\Http\Controllers\Controller;
use App\Imports\StudentImport;
use App\Models\LecturerPosition;
use App\Models\Student;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{
    /** Riwayat akademik milik mahasiswa yang sedang login. */
    public function myAcademicHistory(Request $request): JsonResponse
    {
        $student = $request->user()?->student;
        if (! $student) {
            return response()->json(['message' => 'Akun ini belum terhubung dengan data mahasiswa.'], 404);
        }

        $student->load([
            'studyProgram:id,code,name',
            'semesterSummaries.semester:id,name,type,start_date,end_date',
            'statusHistories.semester:id,name,type,start_date,end_date',
        ]);

        $summaries = $student->semesterSummaries
            ->sortByDesc(fn ($summary) => $summary->semester?->start_date?->timestamp ?? 0)
            ->values();
        $statusHistories = $student->statusHistories
            ->sortByDesc(fn ($history) => $history->semester?->start_date?->timestamp ?? 0)
            ->values();

        return response()->json([
            'student' => $student->only(['id', 'nim', 'name', 'status', 'current_semester']),
            'study_program' => $student->studyProgram,
            'summaries' => $summaries,
            'status_histories' => $statusHistories,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        // Jika user adalah Kaprodi, filter hanya mahasiswa prodi-nya
        $kaprodiProdiId = $this->getKaprodiProdiId();

        $data = Student::with(['studyProgram.faculty', 'advisor', 'academicYear'])
            ->when($kaprodiProdiId, fn ($q) => $q->where('study_program_id', $kaprodiProdiId))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('nim', 'like', "%{$request->search}%"))
            ->when($request->study_program_id && ! $kaprodiProdiId, fn ($q) => $q->where('study_program_id', $request->study_program_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->entry_year, fn ($q) => $q->where('entry_year', $request->entry_year))
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    /** Ambil prodi ID jika user saat ini adalah Kaprodi (bukan admin) */
    private function getKaprodiProdiId(): ?int
    {
        $user = auth()->user();
        if ($user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN_AKADEMIK')) {
            return null; // Admin bisa lihat semua
        }
        $lecturer = $user->lecturer;
        if (! $lecturer) {
            return null;
        }

        $position = LecturerPosition::where('lecturer_id', $lecturer->id)
            ->where('is_active', true)
            ->whereIn('position_code', ['KAPRODI', 'SEKPRODI'])
            ->where('scope_type', 'study_program')
            ->first();

        return $position?->scope_id;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => 'required|exists:study_programs,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'advisor_id' => 'nullable|exists:lecturers,id',
            'nim' => 'required|string|max:20|unique:students',
            'name' => 'required|string|max:255',
            'gender' => 'nullable|in:L,P',
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'origin_school' => 'nullable|string|max:255',
            'entry_year' => 'nullable|integer|digits:4',
            'status' => ['sometimes', Rule::in(Student::STATUSES)],
        ]);

        // Buat akun user otomatis
        $email = $validated['email'] ?? $validated['nim'].'@student.jawami.ac.id';
        $user = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'username' => $validated['nim'],
            'password' => Hash::make($validated['nim']),
        ]);
        $user->assignRole('MAHASISWA');
        $validated['user_id'] = $user->id;

        $student = Student::create($validated);

        // Kirim notifikasi ke dosen wali jika ditunjuk
        if (! empty($validated['advisor_id'])) {
            NotificationService::advisorAssigned($validated['advisor_id'], $student->name, $student->nim);
        }

        return response()->json([
            'message' => 'Mahasiswa berhasil ditambahkan.',
            'data' => $student->load('studyProgram'),
        ], 201);
    }

    public function show(Student $student): JsonResponse
    {
        return response()->json($student->load([
            'studyProgram.faculty',
            'advisor',
            'academicYear',
            'user',
            'profile',
            'addresses',
            'parents',
            'documents',
            'educationHistories',
            'statusHistories.semester',
            'semesterSummaries.semester',
            'financialRecords.semester',
            'pmbRegistrant.period',
            'pmbRegistrant.path',
            'pmbRegistrant.selectionResult',
        ]));
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => 'sometimes|exists:study_programs,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'advisor_id' => 'nullable|exists:lecturers,id',
            'name' => 'sometimes|string|max:255',
            'gender' => 'nullable|in:L,P',
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'origin_school' => 'nullable|string|max:255',
            'entry_year' => 'nullable|integer|digits:4',
            'status' => ['sometimes', Rule::in(Student::STATUSES)],
            'current_semester' => 'nullable|integer|min:1|max:14',
        ]);

        $oldAdvisorId = $student->advisor_id;
        $student->update($validated);

        // Kirim notifikasi jika dosen wali berubah
        if (isset($validated['advisor_id']) && $validated['advisor_id'] && $validated['advisor_id'] != $oldAdvisorId) {
            NotificationService::advisorAssigned($validated['advisor_id'], $student->name, $student->nim);
        }

        return response()->json([
            'message' => 'Data mahasiswa berhasil diupdate.',
            'data' => $student->fresh('studyProgram'),
        ]);
    }

    public function destroy(Student $student): JsonResponse
    {
        $student->delete();

        return response()->json(['message' => 'Data mahasiswa berhasil dihapus.']);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'exists:students,id']);
        $count = Student::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => "{$count} data mahasiswa berhasil dihapus."]);
    }

    public function export(Request $request)
    {
        $filename = 'mahasiswa-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(
            new StudentExport($request->study_program_id, $request->status),
            $filename
        );
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        $import = new StudentImport;
        Excel::import($import, $request->file('file'));

        $errors = collect($import->errors())->map(fn ($e) => $e->getMessage())->values();

        return response()->json([
            'message' => 'Import selesai.'.($errors->count() ? ' Beberapa baris dilewati.' : ''),
            'errors' => $errors,
        ]);
    }
}
