<?php

namespace App\Http\Middleware;

use App\Models\ClassModel;
use App\Models\Lecturer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClassLecturer
{
    /**
     * Pastikan user yang mengakses kelas adalah dosen pengampu kelas tersebut,
     * atau admin/kaprodi yang punya akses penuh.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admin & Kaprodi bypass
        if ($user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN_AKADEMIK')) {
            return $next($request);
        }

        // Cek jabatan Kaprodi (punya akses ke semua kelas di prodinya)
        $lecturer = Lecturer::where('user_id', $user->id)->first();
        if ($lecturer) {
            $isKaprodi = \App\Models\LecturerPosition::where('lecturer_id', $lecturer->id)
                ->where('is_active', true)
                ->whereIn('position_code', ['KAPRODI', 'SEKPRODI'])
                ->exists();
            if ($isKaprodi) {
                return $next($request);
            }
        }

        // Ambil class dari route parameter
        $class = $request->route('class');
        if (!$class instanceof ClassModel) {
            $classId = $request->route('class');
            $class = ClassModel::find($classId);
        }

        if (!$class) {
            return response()->json(['message' => 'Kelas tidak ditemukan.'], 404);
        }

        // Cek apakah dosen adalah pengampu kelas ini
        if (!$lecturer || $class->lecturer_id !== $lecturer->id) {
            return response()->json([
                'message' => 'Anda bukan pengampu mata kuliah di kelas ini.',
            ], 403);
        }

        return $next($request);
    }
}
