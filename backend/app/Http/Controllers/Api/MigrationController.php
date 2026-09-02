<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Controller migrasi sementara dari SIAKAD ke ASC.
 * HAPUS setelah migrasi selesai!
 */
class MigrationController extends Controller
{
    /** Kunci keamanan — harus dikirim di header X-Migration-Key */
    private const MIGRATION_KEY = 'SIAKAD-MIGRATE-2026-ASC';

    private function checkKey(Request $request): bool
    {
        return $request->header('X-Migration-Key') === self::MIGRATION_KEY;
    }

    /**
     * Step 1: Upload file SQL dump dalam satu request.
     * POST /api/migration/upload
     * Header: X-Migration-Key: SIAKAD-MIGRATE-2026-ASC
     * Body: multipart, field "sql_file"
     */
    public function upload(Request $request)
    {
        if (!$this->checkKey($request)) return response()->json(['error' => 'Unauthorized'], 401);

        $request->validate(['sql_file' => 'required|file|max:102400']); // max 100MB

        $path = $request->file('sql_file')->storeAs('migration', 'siakad.sql', 'local');

        return response()->json([
            'message' => 'File berhasil diupload.',
            'path' => $path,
            'size_mb' => round($request->file('sql_file')->getSize() / 1048576, 2),
        ]);
    }

    /**
     * Step 1b: Upload chunk (untuk file besar > 8MB).
     * POST /api/migration/upload-chunk
     * Header: X-Migration-Key, X-Chunk-Number, X-Total-Chunks, X-Total-Size
     * Body: multipart, field "chunk"
     */
    public function uploadChunk(Request $request)
    {
        if (!$this->checkKey($request)) return response()->json(['error' => 'Unauthorized'], 401);

        $request->validate(['chunk' => 'required|file']);

        $chunkNum   = (int) $request->header('X-Chunk-Number', 0);
        $totalChunks = (int) $request->header('X-Total-Chunks', 1);

        $chunkPath = storage_path("app/migration/chunks/chunk_{$chunkNum}.bin");
        @mkdir(dirname($chunkPath), 0755, true);
        $request->file('chunk')->move(dirname($chunkPath), basename($chunkPath));

        return response()->json([
            'success' => true,
            'message' => "Chunk {$chunkNum}/{$totalChunks} diterima.",
            'chunk' => $chunkNum,
        ]);
    }

    /**
     * Step 1c: Gabungkan semua chunk menjadi satu file SQL.
     * POST /api/migration/assemble-chunks
     * Header: X-Migration-Key, X-Total-Chunks
     */
    public function assembleChunks(Request $request)
    {
        if (!$this->checkKey($request)) return response()->json(['error' => 'Unauthorized'], 401);

        $totalChunks = (int) $request->header('X-Total-Chunks', 1);
        $chunkDir = storage_path('app/migration/chunks');
        $outputPath = storage_path('app/migration/siakad.sql');

        @mkdir(dirname($outputPath), 0755, true);

        $out = fopen($outputPath, 'wb');
        if (!$out) return response()->json(['error' => 'Tidak bisa membuat file output.'], 500);

        $missing = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = "{$chunkDir}/chunk_{$i}.bin";
            if (!file_exists($chunkFile)) {
                $missing[] = $i;
                continue;
            }
            $data = file_get_contents($chunkFile);
            fwrite($out, $data);
            unlink($chunkFile);
        }
        fclose($out);

        if (!empty($missing)) {
            return response()->json(['error' => 'Chunk hilang: ' . implode(', ', $missing)], 422);
        }

        $sizeMb = round(filesize($outputPath) / 1048576, 2);
        return response()->json([
            'message' => "File SQL berhasil digabungkan. Ukuran: {$sizeMb} MB",
            'size_mb' => $sizeMb,
        ]);
    }

    /**
     * Step 2: Dry-run — lihat preview tanpa mengubah data.
     * POST /api/migration/dry-run
     * Header: X-Migration-Key: SIAKAD-MIGRATE-2026-ASC
     * Body JSON: { "table": "all" } (opsional)
     */
    public function dryRun(Request $request)
    {
        if (!$this->checkKey($request)) return response()->json(['error' => 'Unauthorized'], 401);

        $sqlPath = storage_path('app/migration/siakad.sql');
        if (!file_exists($sqlPath)) {
            return response()->json(['error' => 'File SQL belum diupload. Jalankan /migration/upload dulu.'], 422);
        }

        $table = $request->input('table', 'all');

        Artisan::call('siakad:migrate', [
            '--source' => $sqlPath,
            '--dry-run' => true,
            '--table' => $table,
        ]);

        return response()->json([
            'message' => 'Dry-run selesai.',
            'output' => Artisan::output(),
        ]);
    }

    /**
     * Step 3: Jalankan migrasi.
     * POST /api/migration/run
     * Header: X-Migration-Key: SIAKAD-MIGRATE-2026-ASC
     * Body JSON: { "table": "faculties" } (opsional, default "all")
     */
    public function run(Request $request)
    {
        if (!$this->checkKey($request)) return response()->json(['error' => 'Unauthorized'], 401);

        $sqlPath = storage_path('app/migration/siakad.sql');
        if (!file_exists($sqlPath)) {
            return response()->json(['error' => 'File SQL belum diupload.'], 422);
        }

        $table = $request->input('table', 'all');

        Artisan::call('siakad:migrate', [
            '--source' => $sqlPath,
            '--table' => $table,
        ]);

        return response()->json([
            'message' => "Migrasi tabel '{$table}' selesai.",
            'output' => Artisan::output(),
        ]);
    }

    /**
     * Hapus file SQL setelah migrasi selesai.
     * DELETE /api/migration/cleanup
     * Header: X-Migration-Key: SIAKAD-MIGRATE-2026-ASC
     */
    public function cleanup(Request $request)
    {
        if (!$this->checkKey($request)) return response()->json(['error' => 'Unauthorized'], 401);

        Storage::disk('local')->delete('migration/siakad.sql');
        // Hapus juga chunks jika ada
        $chunkDir = storage_path('app/migration/chunks');
        if (is_dir($chunkDir)) array_map('unlink', glob("{$chunkDir}/*.bin"));

        return response()->json(['message' => 'File SQL berhasil dihapus.']);
    }

    /**
     * Download file SQL dari URL eksternal (Google Drive, Dropbox, dll).
     * POST /api/migration/download-from-url
     * Header: X-Migration-Key: SIAKAD-MIGRATE-2026-ASC
     * Body JSON: { "url": "https://..." }
     */
    public function downloadFromUrl(Request $request)
    {
        if (!$this->checkKey($request)) return response()->json(['error' => 'Unauthorized'], 401);

        $request->validate(['url' => 'required|url']);
        $url = $request->input('url');

        $outputPath = storage_path('app/migration/siakad.sql');
        @mkdir(dirname($outputPath), 0755, true);

        // Download dengan stream (hemat memory)
        $context = stream_context_create([
            'http' => ['timeout' => 300, 'follow_location' => true],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $in  = fopen($url, 'rb', false, $context);
        if (!$in) return response()->json(['error' => 'Gagal membuka URL. Pastikan URL valid dan public.'], 422);

        $out = fopen($outputPath, 'wb');
        $bytes = 0;
        while (!feof($in)) {
            $chunk = fread($in, 65536); // 64KB chunks
            fwrite($out, $chunk);
            $bytes += strlen($chunk);
        }
        fclose($in);
        fclose($out);

        $sizeMb = round($bytes / 1048576, 2);
        return response()->json([
            'message' => "File SQL berhasil didownload dari URL.",
            'size_mb' => $sizeMb,
            'bytes'   => $bytes,
        ]);
    }

    /** Cek apakah file SQL sudah ada di server */
    public function status(Request $request)
    {
        if (!$this->checkKey($request)) return response()->json(['error' => 'Unauthorized'], 401);

        $sqlPath = storage_path('app/migration/siakad.sql');
        $exists = file_exists($sqlPath);
        $sizeMb = $exists ? round(filesize($sqlPath) / 1048576, 2) : 0;

        return response()->json([
            'exists'  => $exists,
            'size_mb' => $sizeMb,
            'path'    => $sqlPath,
        ]);
    }
}
