<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileController extends Controller
{
    /** Upload file ke model tertentu */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'        => 'required|file|max:10240', // max 10MB
            'owner_type'  => 'required|string',
            'owner_id'    => 'required|integer',
            'collection'  => 'nullable|string|max:50',
            'replace'     => 'nullable|boolean', // true = replace file lama di collection
        ]);

        // Resolve model
        $modelClass = $this->resolveModelClass($request->owner_type);
        if (!$modelClass) {
            return response()->json(['message' => 'Tipe owner tidak valid.'], 422);
        }

        $owner = $modelClass::findOrFail($request->owner_id);

        if (!method_exists($owner, 'files')) {
            return response()->json(['message' => 'Model tidak mendukung file upload.'], 422);
        }

        $collection = $request->collection ?? 'default';
        $uploadedFile = $request->file('file');

        if ($request->boolean('replace')) {
            $file = $owner->setFile($uploadedFile, $collection);
        } else {
            $file = $owner->addFile($uploadedFile, $collection);
        }

        return response()->json([
            'message' => 'File berhasil diupload.',
            'data'    => $file,
        ], 201);
    }

    /** List files per owner */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'owner_type' => 'required|string',
            'owner_id'   => 'required|integer',
            'collection' => 'nullable|string',
        ]);

        $query = File::where('owner_type', $this->resolveOwnerType($request->owner_type))
            ->where('owner_id', $request->owner_id);

        if ($request->collection) {
            $query->where('collection', $request->collection);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    /** Hapus file */
    public function destroy(File $file): JsonResponse
    {
        $file->deleteFile();
        return response()->json(['message' => 'File berhasil dihapus.']);
    }

    /** Resolve short name ke fully qualified class */
    private function resolveModelClass(string $type): ?string
    {
        $map = [
            'student'        => \App\Models\Student::class,
            'lecturer'       => \App\Models\Lecturer::class,
            'staff'          => \App\Models\Staff::class,
            'institution'    => \App\Models\Institution::class,
            'pmb_registrant' => \App\Models\PmbRegistrant::class,
            'rps'            => \App\Models\Rps::class,
            'curriculum'     => \App\Models\Curriculum::class,
            'krs'            => \App\Models\Krs::class,
        ];

        return $map[strtolower($type)] ?? null;
    }

    private function resolveOwnerType(string $type): string
    {
        $class = $this->resolveModelClass($type);
        return $class ?? $type;
    }
}
