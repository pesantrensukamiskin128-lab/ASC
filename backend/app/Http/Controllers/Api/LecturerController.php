<?php

namespace App\Http\Controllers\Api;

use App\Exports\LecturerExport;
use App\Http\Controllers\Controller;
use App\Imports\LecturerImport;
use App\Models\Lecturer;
use App\Models\LecturerPosition;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class LecturerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Jika user adalah Kaprodi, filter hanya dosen prodi-nya
        $kaprodiProdiId = $this->getKaprodiProdiId();

        $data = Lecturer::with(['studyProgram.faculty', 'activePositions'])
            ->when($kaprodiProdiId, fn ($q) => $q->where('study_program_id', $kaprodiProdiId))
            ->when($request->search, fn ($q) => $q->where(fn ($searchQuery) => $searchQuery
                ->where('full_name', 'like', "%{$request->search}%")
                ->orWhere('nidn', 'like', "%{$request->search}%")
                ->orWhere('nuptk', 'like', "%{$request->search}%")))
            ->when($request->study_program_id && ! $kaprodiProdiId, fn ($q) => $q->where('study_program_id', $request->study_program_id))
            ->when($request->status !== null, fn ($q) => $q->where('status', $request->boolean('status')))
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    /** Ambil prodi ID jika user saat ini adalah Kaprodi (bukan admin) */
    private function getKaprodiProdiId(): ?int
    {
        $user = auth()->user();
        if ($user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN_AKADEMIK')) {
            return null;
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
            'study_program_id' => 'nullable|exists:study_programs,id',
            'nidn' => 'nullable|string|max:20|unique:lecturers',
            'nuptk' => 'nullable|string|max:20|unique:lecturers',
            'nip' => 'nullable|string|max:30',
            'degree_front' => 'nullable|string|max:50',
            'degree_back' => 'nullable|string|max:100',
            'full_name' => 'required|string|max:255',
            'gender' => 'nullable|in:L,P',
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'academic_rank' => 'nullable|string|max:100',
            'employment_status' => 'nullable|string|max:50',
            'status' => 'sometimes|boolean',
        ]);

        // Buat akun user otomatis jika NIDN tersedia
        if (! empty($validated['nidn'])) {
            $email = $validated['email'] ?? $validated['nidn'].'@dosen.jawami.ac.id';
            $user = User::firstOrCreate(
                ['username' => $validated['nidn']],
                ['name' => $validated['full_name'], 'email' => $email, 'password' => Hash::make($validated['nidn'])]
            );
            if ($user->wasRecentlyCreated) {
                $user->assignRole('DOSEN');
            }
            $validated['user_id'] = $user->id;
        }

        $lecturer = Lecturer::create($validated);

        return response()->json([
            'message' => 'Data dosen berhasil ditambahkan.',
            'data' => $lecturer->load('studyProgram'),
        ], 201);
    }

    public function show(Lecturer $lecturer): JsonResponse
    {
        return response()->json($lecturer->load(['studyProgram.faculty', 'user', 'activePositions']));
    }

    public function update(Request $request, Lecturer $lecturer): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => 'nullable|exists:study_programs,id',
            'nidn' => "nullable|string|max:20|unique:lecturers,nidn,{$lecturer->id}",
            'nuptk' => "nullable|string|max:20|unique:lecturers,nuptk,{$lecturer->id}",
            'nip' => 'nullable|string|max:30',
            'degree_front' => 'nullable|string|max:50',
            'degree_back' => 'nullable|string|max:100',
            'full_name' => 'sometimes|string|max:255',
            'gender' => 'nullable|in:L,P',
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'academic_rank' => 'nullable|string|max:100',
            'employment_status' => 'nullable|string|max:50',
            'status' => 'sometimes|boolean',
        ]);

        $lecturer->update($validated);

        return response()->json([
            'message' => 'Data dosen berhasil diupdate.',
            'data' => $lecturer->fresh('studyProgram'),
        ]);
    }

    public function destroy(Lecturer $lecturer): JsonResponse
    {
        if ($lecturer->photo_path) {
            Storage::disk('public')->delete($lecturer->photo_path);
        }
        $lecturer->delete();

        return response()->json(['message' => 'Data dosen berhasil dihapus.']);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'exists:lecturers,id']);
        $count = Lecturer::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => "{$count} data dosen berhasil dihapus."]);
    }

    public function uploadPhoto(Request $request, Lecturer $lecturer): JsonResponse
    {
        $request->validate(['photo' => 'required|image|mimes:jpeg,png,webp|max:2048']);

        if ($lecturer->photo_path) {
            Storage::disk('public')->delete($lecturer->photo_path);
        }

        $path = $request->file('photo')->store('lecturers/photos', 'public');
        $lecturer->update(['photo_path' => $path]);

        return response()->json([
            'message' => 'Foto berhasil diupload.',
            'photo_path' => $path,
            'photo_url' => Storage::disk('public')->url($path),
        ]);
    }

    public function export(Request $request)
    {
        $filename = 'dosen-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new LecturerExport($request->study_program_id), $filename);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        $import = new LecturerImport;
        Excel::import($import, $request->file('file'));

        $errors = collect($import->errors())->map(fn ($e) => $e->getMessage())->values();

        return response()->json([
            'message' => 'Import selesai.'.($errors->count() ? ' Beberapa baris dilewati.' : ''),
            'errors' => $errors,
        ]);
    }

    public function all(Request $request): JsonResponse
    {
        return response()->json(
            Lecturer::where('status', true)
                ->when($request->study_program_id, fn ($q) => $q->where('study_program_id', $request->study_program_id))
                ->select('id', 'nidn', 'full_name', 'degree_front', 'degree_back')
                ->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'nidn' => $l->nidn,
                    'name' => $l->name,
                ])
        );
    }
}
