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
     * Step 1: Upload file SQL dump.
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

        return response()->json(['message' => 'File SQL berhasil dihapus.']);
    }
}
