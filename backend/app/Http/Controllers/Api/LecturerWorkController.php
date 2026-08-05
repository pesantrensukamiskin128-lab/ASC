<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LecturerWork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LecturerWorkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user    = auth()->user();
        $isAdmin = $user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN_AKADEMIK')
                   || $user->hasRole('LP2M')
                   || $user->hasPermission('karya.verify');

        $query = LecturerWork::with(['lecturer'])
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->when($request->year, fn($q) => $q->where('year', $request->year));

        // Dosen hanya lihat karya miliknya
        if (!$isAdmin && $user->lecturer) {
            $query->where('lecturer_id', $user->lecturer->id);
        } elseif (!$isAdmin) {
            $query->whereRaw('1 = 0');
        }

        $data = $query->orderByDesc('created_at')->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function show(LecturerWork $lecturerWork): JsonResponse
    {
        $user    = auth()->user();
        $isAdmin = $user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN_AKADEMIK')
                   || $user->hasRole('LP2M')
                   || $user->hasPermission('karya.verify');

        if (!$isAdmin && $user->lecturer?->id !== $lecturerWork->lecturer_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        return response()->json($lecturerWork->load(['lecturer', 'verifiedBy', 'publishedBy']));
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->lecturer) {
            return response()->json(['message' => 'Akun bukan dosen.'], 403);
        }

        $validated = $request->validate([
            'type'           => 'required|in:buku,modul_ajar,hki_paten,penelitian_mandiri,pengabdian_mandiri',
            'title'          => 'required|string|max:500',
            'year'           => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'description'    => 'nullable|string',
            'keywords'       => 'nullable|string|max:500',
            'publisher'      => 'nullable|string|max:255',
            'isbn_issn'      => 'nullable|string|max:100',
            'hki_number'     => 'nullable|string|max:100',
            'published_date' => 'nullable|date',
            'main_file'      => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'support_file'   => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $data = collect($validated)->except(['main_file', 'support_file'])->toArray();
        $data['lecturer_id'] = $user->lecturer->id;
        $data['status']      = 'draft';

        if ($request->hasFile('main_file')) {
            $data['main_file_path'] = $request->file('main_file')->store('lecturer-works', 'public');
        }
        if ($request->hasFile('support_file')) {
            $data['support_file_path'] = $request->file('support_file')->store('lecturer-works', 'public');
        }

        $work = LecturerWork::create($data);
        return response()->json(['message' => 'Karya berhasil disimpan.', 'data' => $work], 201);
    }

    public function update(Request $request, LecturerWork $lecturerWork): JsonResponse
    {
        $user = auth()->user();

        // Hanya pemilik yang bisa edit, dan hanya saat draft atau revisi
        if ($user->lecturer?->id !== $lecturerWork->lecturer_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }
        if (!in_array($lecturerWork->status, ['draft', 'revisi'])) {
            return response()->json(['message' => 'Karya hanya bisa diedit saat berstatus draft atau revisi.'], 422);
        }

        $validated = $request->validate([
            'type'           => 'sometimes|in:buku,modul_ajar,hki_paten,penelitian_mandiri,pengabdian_mandiri',
            'title'          => 'sometimes|string|max:500',
            'year'           => 'sometimes|integer|min:2000|max:' . (date('Y') + 1),
            'description'    => 'nullable|string',
            'keywords'       => 'nullable|string|max:500',
            'publisher'      => 'nullable|string|max:255',
            'isbn_issn'      => 'nullable|string|max:100',
            'hki_number'     => 'nullable|string|max:100',
            'published_date' => 'nullable|date',
            'main_file'      => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'support_file'   => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $data = collect($validated)->except(['main_file', 'support_file'])->toArray();

        if ($request->hasFile('main_file')) {
            if ($lecturerWork->main_file_path) Storage::disk('public')->delete($lecturerWork->main_file_path);
            $data['main_file_path'] = $request->file('main_file')->store('lecturer-works', 'public');
        }
        if ($request->hasFile('support_file')) {
            if ($lecturerWork->support_file_path) Storage::disk('public')->delete($lecturerWork->support_file_path);
            $data['support_file_path'] = $request->file('support_file')->store('lecturer-works', 'public');
        }

        $lecturerWork->update($data);
        return response()->json(['message' => 'Karya berhasil diupdate.', 'data' => $lecturerWork->fresh()]);
    }

    public function destroy(LecturerWork $lecturerWork): JsonResponse
    {
        $user = auth()->user();
        if ($user->lecturer?->id !== $lecturerWork->lecturer_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }
        if (!in_array($lecturerWork->status, ['draft', 'revisi'])) {
            return response()->json(['message' => 'Karya hanya bisa dihapus saat berstatus draft atau revisi.'], 422);
        }

        // Hapus file
        if ($lecturerWork->main_file_path) Storage::disk('public')->delete($lecturerWork->main_file_path);
        if ($lecturerWork->support_file_path) Storage::disk('public')->delete($lecturerWork->support_file_path);
        if ($lecturerWork->cover_image_path) Storage::disk('public')->delete($lecturerWork->cover_image_path);

        $lecturerWork->delete();
        return response()->json(['message' => 'Karya berhasil dihapus.']);
    }

    /** Dosen ajukan ke LP2M */
    public function submit(LecturerWork $lecturerWork): JsonResponse
    {
        $user = auth()->user();
        if ($user->lecturer?->id !== $lecturerWork->lecturer_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }
        if (!in_array($lecturerWork->status, ['draft', 'revisi'])) {
            return response()->json(['message' => 'Hanya karya berstatus draft atau revisi yang bisa diajukan.'], 422);
        }

        $lecturerWork->update([
            'status'       => 'diajukan',
            'revision_note'=> null,   // hapus catatan revisi lama
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);

        // Notifikasi ke admin/LP2M (kirim ke semua user yang punya role ADMIN_AKADEMIK)
        $adminUsers = \App\Models\User::role('ADMIN_AKADEMIK')->get();
        foreach ($adminUsers as $admin) {
            \App\Models\AppNotification::send(
                $admin->id,
                'Karya Dosen Diajukan',
                "{$user->name} mengajukan karya: {$lecturerWork->title}",
                'info',
                '/karya-dosen/' . $lecturerWork->id
            );
        }

        return response()->json(['message' => 'Karya berhasil diajukan ke LP2M.']);
    }

    /** LP2M verifikasi atau minta revisi */
    public function verify(Request $request, LecturerWork $lecturerWork): JsonResponse
    {
        $request->validate([
            'action'        => 'required|in:verify,revision',
            'revision_note' => 'nullable|string',
        ]);

        if ($lecturerWork->status !== 'diajukan') {
            return response()->json(['message' => 'Hanya karya berstatus diajukan yang bisa diverifikasi.'], 422);
        }

        if ($request->action === 'verify') {
            $lecturerWork->update([
                'status'      => 'diverifikasi',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'revision_note' => null,
            ]);
            $msg = 'Karya Anda telah diverifikasi oleh LP2M dan siap dipublikasikan.';
            $type = 'success';
        } else {
            if (!$request->revision_note) {
                return response()->json(['message' => 'Catatan revisi wajib diisi.'], 422);
            }
            $lecturerWork->update([
                'status'        => 'revisi',
                'revision_note' => $request->revision_note,
            ]);
            $msg  = "Karya Anda memerlukan revisi. Catatan: {$request->revision_note}";
            $type = 'warning';
        }

        // Notifikasi ke dosen
        if ($lecturerWork->lecturer?->user_id) {
            \App\Models\AppNotification::send(
                $lecturerWork->lecturer->user_id,
                'Status Karya Dosen',
                $msg, $type,
                '/karya-dosen/' . $lecturerWork->id
            );
        }

        return response()->json(['message' => 'Verifikasi berhasil.', 'data' => $lecturerWork->fresh()]);
    }

    /** LP2M publikasi ke repository */
    public function publish(Request $request, LecturerWork $lecturerWork): JsonResponse
    {
        if ($lecturerWork->status !== 'diverifikasi') {
            return response()->json(['message' => 'Hanya karya berstatus diverifikasi yang bisa dipublikasikan.'], 422);
        }

        $request->validate([
            'repository_url' => 'nullable|string|max:500',
            'cover_image'    => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $coverPath = $lecturerWork->cover_image_path;
        if ($request->hasFile('cover_image')) {
            if ($coverPath) Storage::disk('public')->delete($coverPath);
            $coverPath = $request->file('cover_image')->store('lecturer-work-covers', 'public');
        }

        $lecturerWork->update([
            'status'           => 'dipublikasikan',
            'repository_url'   => $request->repository_url,
            'cover_image_path' => $coverPath,
            'published_by'     => auth()->id(),
            'published_at'     => now(),
        ]);

        // Notifikasi ke dosen
        if ($lecturerWork->lecturer?->user_id) {
            \App\Models\AppNotification::send(
                $lecturerWork->lecturer->user_id,
                '🎉 Karya Dipublikasikan',
                "Karya Anda \"{$lecturerWork->title}\" telah dipublikasikan ke repository.",
                'success',
                '/karya-dosen/' . $lecturerWork->id
            );
        }

        return response()->json(['message' => 'Karya berhasil dipublikasikan.']);
    }

    /** Statistik untuk dashboard */
    public function stats(): JsonResponse
    {
        $user    = auth()->user();
        $isAdmin = $user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN_AKADEMIK')
                   || $user->hasRole('LP2M')
                   || $user->hasPermission('karya.verify');

        $query = LecturerWork::query();
        if (!$isAdmin && $user->lecturer) {
            $query->where('lecturer_id', $user->lecturer->id);
        }

        return response()->json([
            'total'          => (clone $query)->count(),
            'draft'          => (clone $query)->where('status', 'draft')->count(),
            'diajukan'       => (clone $query)->where('status', 'diajukan')->count(),
            'revisi'         => (clone $query)->where('status', 'revisi')->count(),
            'diverifikasi'   => (clone $query)->where('status', 'diverifikasi')->count(),
            'dipublikasikan' => (clone $query)->where('status', 'dipublikasikan')->count(),
        ]);
    }
}
