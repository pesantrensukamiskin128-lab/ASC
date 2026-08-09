<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Institution::all());
    }

    /** Serve logo institusi sebagai file response (untuk canvas / cross-origin) */
    public function logo()
    {
        $institution = Institution::first();
        if (!$institution?->logo_path) {
            abort(404, 'Logo not found');
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (!$disk->exists($institution->logo_path)) {
            abort(404, 'Logo file not found');
        }

        $mime = $disk->mimeType($institution->logo_path) ?: 'image/png';
        $content = $disk->get($institution->logo_path);

        return response($content, 200)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function public(): JsonResponse
    {
        $institution = Institution::select(
            'id', 'name', 'short_name', 'legal_entity_name', 'logo_path', 'letterhead_path', 'accreditation'
        )->first();

        if (!$institution) {
            return response()->json([
                'id'                => null,
                'name'              => 'Al-Jawami Smart Campus',
                'short_name'        => 'ASC',
                'legal_entity_name' => null,
                'logo_path'         => null,
                'logo_url'          => null,
                'letterhead_path'   => null,
                'letterhead_url'    => null,
                'accreditation'     => null,
            ]);
        }

        $institution->logo_url      = $this->resolveStorageUrl($institution->logo_path);
        $institution->letterhead_url = $this->resolveStorageUrl($institution->letterhead_path);

        return response()->json($institution);
    }

    /**
     * Resolve storage path ke absolute URL yang benar di semua environment.
     * Menggunakan APP_URL eksplisit agar tidak bergantung pada request host,
     * yang penting untuk Railway/reverse proxy.
     */
    private function resolveStorageUrl(?string $path): ?string
    {
        if (!$path) return null;

        // Cek apakah file ada di storage
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return null;
        }

        $appUrl = rtrim(config('app.url'), '/');

        // Pastikan APP_URL punya scheme — jika tidak, tambahkan https://
        if (!str_starts_with($appUrl, 'http://') && !str_starts_with($appUrl, 'https://')) {
            $appUrl = 'https://' . $appUrl;
        }

        return $appUrl . '/storage/' . ltrim($path, '/');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'              => 'required|string|max:50|unique:institutions',
            'name'              => 'required|string|max:255',
            'short_name'        => 'nullable|string|max:100',
            'legal_entity_name' => 'nullable|string|max:255',
            'address'           => 'nullable|string',
            'phone'             => 'nullable|string|max:20',
            'email'             => 'nullable|email',
            'website'           => 'nullable|string|max:255',
            'accreditation'     => 'nullable|string|max:20',
        ]);

        $institution = Institution::create($validated);

        return response()->json(['message' => 'Institusi berhasil ditambahkan.', 'data' => $institution], 201);
    }

    public function show(Institution $institution): JsonResponse
    {
        return response()->json($institution->load('faculties'));
    }

    public function update(Request $request, Institution $institution): JsonResponse
    {
        $validated = $request->validate([
            'code'              => "sometimes|string|max:50|unique:institutions,code,{$institution->id}",
            'name'              => 'sometimes|string|max:255',
            'short_name'        => 'nullable|string|max:100',
            'legal_entity_name' => 'nullable|string|max:255',
            'address'           => 'nullable|string',
            'phone'             => 'nullable|string|max:20',
            'email'             => 'nullable|email',
            'website'           => 'nullable|string|max:255',
            'accreditation'     => 'nullable|string|max:20',
            'logo_path'         => 'nullable|string',
        ]);

        // Hapus file lama jika logo_path di-set null
        if (array_key_exists('logo_path', $validated) && is_null($validated['logo_path'])) {
            if ($institution->logo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($institution->logo_path);
            }
        }

        $institution->update($validated);

        return response()->json(['message' => 'Institusi berhasil diupdate.', 'data' => $institution->fresh()]);
    }

    public function destroy(Institution $institution): JsonResponse
    {
        if ($institution->faculties()->count() > 0) {
            return response()->json(['message' => 'Tidak dapat menghapus institusi yang masih memiliki fakultas.'], 422);
        }

        $institution->delete();

        return response()->json(['message' => 'Institusi berhasil dihapus.']);
    }

    public function uploadLogo(Request $request, Institution $institution): JsonResponse
    {
        $request->validate(['logo' => 'required|image|mimes:jpeg,png,svg,webp|max:2048']);

        if ($institution->logo_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($institution->logo_path);
        }

        $path = $request->file('logo')->store('logos', 'public');
        $institution->update(['logo_path' => $path]);

        return response()->json([
            'message'   => 'Logo berhasil diupload.',
            'logo_path' => $path,
            'logo_url'  => $this->resolveStorageUrl($path),
            'data'      => $institution->fresh(),
        ]);
    }

    public function uploadLetterhead(Request $request, Institution $institution): JsonResponse
    {
        $request->validate(['letterhead' => 'required|image|mimes:jpeg,png,webp|max:4096']);

        if ($institution->letterhead_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($institution->letterhead_path);
        }

        $path = $request->file('letterhead')->store('letterheads', 'public');
        $institution->update(['letterhead_path' => $path]);

        return response()->json([
            'message'          => 'Kop surat berhasil diupload.',
            'letterhead_path'  => $path,
            'letterhead_url'   => $this->resolveStorageUrl($path),
            'data'             => $institution->fresh(),
        ]);
    }
}
