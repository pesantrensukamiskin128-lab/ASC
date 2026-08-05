<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LecturerWork;
use App\Models\Penelitian;
use App\Models\Thesis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RepositoryController extends Controller
{
    private function storageUrl(?string $path): ?string
    {
        if (!$path) return null;
        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }

    // =========================================================
    // UNIFIED SEARCH — semua jenis karya digabung
    // =========================================================

    public function index(Request $request): JsonResponse
    {
        $search    = $request->search;
        $type      = $request->type;      // penelitian|pengabdian|skripsi|buku|modul_ajar|hki_paten|penelitian_mandiri|pengabdian_mandiri
        $year      = $request->year;
        $prodiId   = $request->study_program_id;
        $perPage   = min((int) ($request->per_page ?? 12), 50);

        $results = collect();

        // --- Penelitian & Pengabdian ---
        if (!$type || in_array($type, ['penelitian', 'pengabdian'])) {
            $q = Penelitian::with(['ketua', 'studyProgram', 'period'])
                ->where('is_published', true)
                ->when($type,    fn($q) => $q->where('type', $type))
                ->when($year,    fn($q) => $q->whereHas('period', fn($q2) => $q2->where('year', $year)))
                ->when($prodiId, fn($q) => $q->where('study_program_id', $prodiId))
                ->when($search,  fn($q) => $q->where(fn($q2) => $q2
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('abstract', 'like', "%{$search}%")
                    ->orWhere('keywords', 'like', "%{$search}%")));

            $q->get()->each(function ($item) use (&$results) {
                $results->push([
                    'id'           => $item->id,
                    'source'       => 'penelitian',
                    'type'         => $item->type,
                    'type_label'   => $item->type === 'penelitian' ? 'Penelitian' : 'Pengabdian',
                    'title'        => $item->title,
                    'author'       => $item->ketua?->full_name ?? $item->ketua?->name ?? '-',
                    'study_program'=> $item->studyProgram?->name,
                    'year'         => $item->period?->year ?? $item->published_at?->year,
                    'abstract'     => $item->abstract,
                    'keywords'     => $item->keywords,
                    'cover_url'    => $this->storageUrl($item->cover_image_path),
                    'published_at' => $item->published_at,
                    'has_file'     => (bool) $item->laporan_final_path,
                ]);
            });
        }

        // --- Skripsi ---
        if (!$type || $type === 'skripsi') {
            $q = Thesis::with(['student.studyProgram', 'supervisors.lecturer'])
                ->where('is_published', true)
                ->when($year,    fn($q) => $q->whereYear('published_at', $year))
                ->when($prodiId, fn($q) => $q->where('study_program_id', $prodiId))
                ->when($search,  fn($q) => $q->where(fn($q2) => $q2
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('abstract', 'like', "%{$search}%")
                    ->orWhere('keywords', 'like', "%{$search}%")));

            $q->get()->each(function ($item) use (&$results) {
                $results->push([
                    'id'           => $item->id,
                    'source'       => 'skripsi',
                    'type'         => 'skripsi',
                    'type_label'   => 'Skripsi',
                    'title'        => $item->title,
                    'author'       => $item->student?->name ?? '-',
                    'nim'          => $item->student?->nim,
                    'study_program'=> $item->studyProgram?->name ?? $item->student?->studyProgram?->name,
                    'year'         => $item->published_at?->year ?? $item->completion_date?->year,
                    'abstract'     => $item->abstract,
                    'keywords'     => $item->keywords,
                    'cover_url'    => $this->storageUrl($item->cover_image_path),
                    'published_at' => $item->published_at,
                    'has_file'     => (bool) ($item->final_pdf_path ?? $item->final_document_path),
                ]);
            });
        }

        // --- Karya Dosen ---
        $karyaTypes = ['buku', 'modul_ajar', 'hki_paten', 'penelitian_mandiri', 'pengabdian_mandiri'];
        if (!$type || in_array($type, $karyaTypes)) {
            $q = LecturerWork::with('lecturer')
                ->where('status', 'dipublikasikan')
                ->when($type && in_array($type, $karyaTypes), fn($q) => $q->where('type', $type))
                ->when($year,   fn($q) => $q->where('year', $year))
                ->when($search, fn($q) => $q->where(fn($q2) => $q2
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('keywords', 'like', "%{$search}%")));

            $q->get()->each(function ($item) use (&$results) {
                $results->push([
                    'id'           => $item->id,
                    'source'       => 'karya_dosen',
                    'type'         => $item->type,
                    'type_label'   => LecturerWork::TYPE_LABELS[$item->type] ?? $item->type,
                    'title'        => $item->title,
                    'author'       => $item->lecturer?->full_name ?? $item->lecturer?->name ?? '-',
                    'study_program'=> null,
                    'year'         => $item->year,
                    'abstract'     => $item->description,
                    'keywords'     => $item->keywords,
                    'cover_url'    => $this->storageUrl($item->cover_image_path),
                    'published_at' => $item->published_at,
                    'has_file'     => (bool) $item->main_file_path,
                ]);
            });
        }

        // Sort by published_at desc, paginate manual
        $sorted  = $results->sortByDesc('published_at')->values();
        $page    = max(1, (int) ($request->page ?? 1));
        $total   = $sorted->count();
        $items   = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data'         => $items,
            'total'        => $total,
            'current_page' => $page,
            'per_page'     => $perPage,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    // =========================================================
    // DETAIL ENDPOINTS (publik, tanpa auth)
    // =========================================================

    public function showPenelitian(int $id): JsonResponse
    {
        $item = Penelitian::with(['ketua', 'studyProgram', 'period', 'members.lecturer', 'members.student'])
            ->where('is_published', true)
            ->findOrFail($id);

        return response()->json([
            'id'             => $item->id,
            'source'         => 'penelitian',
            'type'           => $item->type,
            'type_label'     => $item->type === 'penelitian' ? 'Penelitian' : 'Pengabdian kepada Masyarakat',
            'title'          => $item->title,
            'abstract'       => $item->abstract,
            'keywords'       => $item->keywords,
            'bibliography'   => $item->bibliography,
            'year'           => $item->period?->year,
            'period'         => $item->period?->name,
            'study_program'  => $item->studyProgram?->name,
            'ketua'          => $item->ketua?->full_name ?? $item->ketua?->name,
            'members'        => $item->members->map(fn($m) => [
                'name' => $m->lecturer?->full_name ?? $m->lecturer?->name ?? $m->student?->name ?? '-',
                'type' => $m->member_type,
            ]),
            'cover_url'      => $this->storageUrl($item->cover_image_path),
            'published_at'   => $item->published_at,
            'repository_url' => $item->repository_url,
            // file hanya tampil path, URL generate saat download (butuh auth)
            'has_laporan_final' => (bool) $item->laporan_final_path,
            'has_paper_final'   => (bool) $item->paper_final_path,
        ]);
    }

    public function showSkripsi(int $id): JsonResponse
    {
        $item = Thesis::with(['student.studyProgram', 'studyProgram', 'supervisors.lecturer'])
            ->where('is_published', true)
            ->findOrFail($id);

        return response()->json([
            'id'             => $item->id,
            'source'         => 'skripsi',
            'type'           => $item->type ?? 'skripsi',
            'type_label'     => 'Skripsi',
            'title'          => $item->title,
            'abstract'       => $item->abstract,
            'keywords'       => $item->keywords,
            'nim'            => $item->student?->nim,
            'author'         => $item->student?->name,
            'study_program'  => $item->studyProgram?->name ?? $item->student?->studyProgram?->name,
            'year'           => $item->published_at?->year ?? $item->completion_date?->year,
            'supervisors'    => $item->supervisors->map(fn($s) => [
                'name' => $s->lecturer?->full_name ?? $s->lecturer?->name ?? '-',
                'role' => $s->role,
            ]),
            'cover_url'      => $this->storageUrl($item->cover_image_path),
            'published_at'   => $item->published_at,
            'repository_url' => $item->repository_url,
            'has_final_pdf'  => (bool) ($item->final_pdf_path ?? $item->final_document_path),
            'has_proposal'   => (bool) $item->proposal_file_url,
        ]);
    }

    public function showKaryaDosen(int $id): JsonResponse
    {
        $item = LecturerWork::with('lecturer')
            ->where('status', 'dipublikasikan')
            ->findOrFail($id);

        return response()->json([
            'id'             => $item->id,
            'source'         => 'karya_dosen',
            'type'           => $item->type,
            'type_label'     => LecturerWork::TYPE_LABELS[$item->type] ?? $item->type,
            'title'          => $item->title,
            'description'    => $item->description,
            'keywords'       => $item->keywords,
            'author'         => $item->lecturer?->full_name ?? $item->lecturer?->name ?? '-',
            'year'           => $item->year,
            'publisher'      => $item->publisher,
            'isbn_issn'      => $item->isbn_issn,
            'hki_number'     => $item->hki_number,
            'published_date' => $item->published_date,
            'cover_url'      => $this->storageUrl($item->cover_image_path),
            'published_at'   => $item->published_at,
            'repository_url' => $item->repository_url,
            'has_main_file'  => (bool) $item->main_file_path,
            'has_support_file' => (bool) $item->support_file_path,
        ]);
    }

    // =========================================================
    // DOWNLOAD — butuh auth
    // =========================================================

    public function download(Request $request, string $source, int $id, string $fileType): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Login diperlukan untuk mengunduh file.'], 401);
        }

        [$path, $filename] = match ($source) {
            'penelitian' => $this->getFilePathPenelitian($id, $fileType),
            'skripsi'    => $this->getFilePathSkripsi($id, $fileType),
            'karya_dosen'=> $this->getFilePathKaryaDosen($id, $fileType),
            default      => [null, null],
        };

        if (!$path || !Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File tidak ditemukan.'], 404);
        }

        return Storage::disk('public')->download($path, $filename);
    }

    private function getFilePathPenelitian(int $id, string $type): array
    {
        $item = Penelitian::where('is_published', true)->findOrFail($id);
        return match ($type) {
            'laporan_final' => [$item->laporan_final_path, "laporan-final-{$item->id}.pdf"],
            'paper_final'   => [$item->paper_final_path,   "paper-final-{$item->id}.pdf"],
            default         => [null, null],
        };
    }

    private function getFilePathSkripsi(int $id, string $type): array
    {
        $item = Thesis::where('is_published', true)->findOrFail($id);
        $path = $item->final_pdf_path ?? $item->final_document_path;
        return match ($type) {
            'skripsi_final' => [$path, "skripsi-{$item->id}.pdf"],
            default         => [null, null],
        };
    }

    private function getFilePathKaryaDosen(int $id, string $type): array
    {
        $item = LecturerWork::where('status', 'dipublikasikan')->findOrFail($id);
        return match ($type) {
            'main_file'    => [$item->main_file_path,    "karya-utama-{$item->id}." . pathinfo($item->main_file_path ?? '', PATHINFO_EXTENSION)],
            'support_file' => [$item->support_file_path, "karya-pendukung-{$item->id}." . pathinfo($item->support_file_path ?? '', PATHINFO_EXTENSION)],
            default        => [null, null],
        };
    }

    // =========================================================
    // STATS publik
    // =========================================================

    public function stats(): JsonResponse
    {
        return response()->json([
            'penelitian'   => Penelitian::where('is_published', true)->where('type', 'penelitian')->count(),
            'pengabdian'   => Penelitian::where('is_published', true)->where('type', 'pengabdian')->count(),
            'skripsi'      => Thesis::where('is_published', true)->count(),
            'karya_dosen'  => LecturerWork::where('status', 'dipublikasikan')->count(),
            'total'        => Penelitian::where('is_published', true)->count()
                            + Thesis::where('is_published', true)->count()
                            + LecturerWork::where('status', 'dipublikasikan')->count(),
        ]);
    }
}

