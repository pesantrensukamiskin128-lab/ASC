<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use App\Models\Schedule;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = ClassModel::with(['course.studyProgram', 'lecturer', 'room', 'semester.academicYear', 'schedules.room'])
            ->withCount('members')
            ->when($request->semester_id, fn($q) => $q->where('semester_id', $request->semester_id))
            ->when($request->study_program_id, fn($q) => $q->where('study_program_id', $request->study_program_id))
            ->when($request->course_id, fn($q) => $q->where('course_id', $request->course_id))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhereHas('course', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")))
            // Dosen biasa hanya lihat kelas yang diampu
            ->when($user->hasRole('DOSEN') && !$user->hasRole('SUPER_ADMIN') && !$user->hasRole('ADMIN_AKADEMIK'), function ($q) use ($user) {
                $lecturer = \App\Models\Lecturer::where('user_id', $user->id)->first();
                if ($lecturer) {
                    // Cek apakah Kaprodi
                    $isKaprodi = \App\Models\LecturerPosition::where('lecturer_id', $lecturer->id)
                        ->where('is_active', true)
                        ->whereIn('position_code', ['KAPRODI', 'SEKPRODI'])
                        ->exists();
                    if (!$isKaprodi) {
                        $q->where('lecturer_id', $lecturer->id);
                    }
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->paginate($request->per_page ?? 20);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => 'required|exists:study_programs,id',
            'semester_id'      => 'required|exists:semesters,id',
            'course_id'        => 'required|exists:courses,id',
            'lecturer_id'      => 'required|exists:lecturers,id',
            'room_id'          => 'nullable|exists:rooms,id',
            'name'             => 'required|string|max:50',
            'capacity'         => 'nullable|integer|min:1',
            'academic_level'   => 'nullable|integer|min:1|max:8',
            'is_active'        => 'boolean',
            // Jadwal (opsional, buat schedule sekaligus)
            'day'              => 'nullable|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'start_time'       => 'nullable|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i|after:start_time',
        ]);

        // Deteksi konflik jadwal
        if (!empty($validated['day']) && !empty($validated['start_time']) && !empty($validated['end_time'])) {
            $conflicts = Schedule::hasConflict(
                $validated['semester_id'],
                $validated['day'],
                $validated['start_time'],
                $validated['end_time'],
                $validated['lecturer_id'],
                $validated['room_id'] ?? null
            );
            if (!empty($conflicts)) {
                return response()->json(['message' => 'Jadwal bentrok!', 'conflicts' => $conflicts], 422);
            }
        }

        $class = DB::transaction(function () use ($validated) {
            $class = ClassModel::create(collect($validated)->only([
                'study_program_id', 'semester_id', 'course_id', 'lecturer_id', 'room_id',
                'name', 'capacity', 'academic_level', 'is_active',
            ])->toArray());

            // Buat jadwal jika disertakan
            if (!empty($validated['day']) && !empty($validated['start_time'])) {
                Schedule::create([
                    'class_id'    => $class->id,
                    'day'         => $validated['day'],
                    'start_time'  => $validated['start_time'],
                    'end_time'    => $validated['end_time'],
                    'room_id'     => $validated['room_id'] ?? null,
                    'lecturer_id' => $validated['lecturer_id'],
                ]);
            }

            return $class;
        });

        // Kirim notifikasi ke dosen yang ditugaskan
        $this->notifyLecturerAssigned($class);

        return response()->json([
            'message' => 'Kelas berhasil ditambahkan.',
            'data'    => $class->load(['course', 'lecturer', 'room', 'schedules']),
        ], 201);
    }

    // Trigger notifikasi ke dosen
    private function notifyLecturerAssigned(ClassModel $class): void
    {
        $class->loadMissing(['course', 'semester']);
        NotificationService::classAssigned(
            $class->lecturer_id,
            $class->course?->name ?? '-',
            $class->name,
            $class->semester?->name ?? '-'
        );
    }

    public function show(ClassModel $class): JsonResponse
    {
        $user = auth()->user();
        if ($user->hasRole('DOSEN') && !$user->hasRole('SUPER_ADMIN') && !$user->hasRole('ADMIN_AKADEMIK')) {
            $lecturer = \App\Models\Lecturer::where('user_id', $user->id)->first();
            $isKaprodi = $lecturer ? \App\Models\LecturerPosition::where('lecturer_id', $lecturer->id)
                ->where('is_active', true)
                ->whereIn('position_code', ['KAPRODI', 'SEKPRODI'])
                ->exists() : false;
            if (!$isKaprodi && (!$lecturer || $class->lecturer_id !== $lecturer->id)) {
                return response()->json(['message' => 'Anda bukan pengampu kelas ini.'], 403);
            }
        }

        return response()->json(
            $class->load(['course.studyProgram', 'lecturer', 'room', 'semester.academicYear', 'schedules.room', 'schedules.lecturer', 'members.student'])
        );
    }

    public function update(Request $request, ClassModel $class): JsonResponse
    {
        $validated = $request->validate([
            'lecturer_id'    => 'sometimes|exists:lecturers,id',
            'room_id'        => 'nullable|exists:rooms,id',
            'name'           => 'sometimes|string|max:50',
            'capacity'       => 'nullable|integer|min:1',
            'academic_level' => 'nullable|integer|min:1|max:8',
            'is_active'      => 'boolean',
        ]);

        $class->update($validated);
        return response()->json(['message' => 'Kelas berhasil diupdate.', 'data' => $class->fresh(['course', 'lecturer', 'room', 'schedules'])]);
    }

    public function destroy(ClassModel $class): JsonResponse
    {
        if ($class->members()->count() > 0) {
            return response()->json(['message' => 'Tidak dapat menghapus kelas yang sudah ada mahasiswa.'], 422);
        }
        $class->delete();
        return response()->json(['message' => 'Kelas berhasil dihapus.']);
    }

    // === JADWAL per KELAS ===

    public function addSchedule(Request $request, ClassModel $class): JsonResponse
    {
        $validated = $request->validate([
            'day'         => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
            'room_id'     => 'nullable|exists:rooms,id',
            'lecturer_id' => 'nullable|exists:lecturers,id',
            'note'        => 'nullable|string|max:100',
        ]);

        $conflicts = Schedule::hasConflict(
            $class->semester_id,
            $validated['day'],
            $validated['start_time'],
            $validated['end_time'],
            $validated['lecturer_id'] ?? $class->lecturer_id,
            $validated['room_id'] ?? $class->room_id
        );

        if (!empty($conflicts)) {
            return response()->json(['message' => 'Jadwal bentrok!', 'conflicts' => $conflicts], 422);
        }

        $schedule = $class->schedules()->create(array_merge($validated, [
            'lecturer_id' => $validated['lecturer_id'] ?? $class->lecturer_id,
        ]));

        return response()->json(['message' => 'Jadwal berhasil ditambahkan.', 'data' => $schedule], 201);
    }

    public function removeSchedule(ClassModel $class, Schedule $schedule): JsonResponse
    {
        $schedule->delete();
        return response()->json(['message' => 'Jadwal berhasil dihapus.']);
    }

    /** List kelas untuk dropdown */
    public function all(Request $request): JsonResponse
    {
        return response()->json(
            ClassModel::with(['course:id,code,name,credits', 'schedules'])
                ->withCount('members')
                ->where('is_active', true)
                ->when($request->semester_id, fn($q) => $q->where('semester_id', $request->semester_id))
                ->when($request->study_program_id, fn($q) => $q->where('study_program_id', $request->study_program_id))
                ->get()
        );
    }
}
