<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Storage File Serving — bypass symlink
|--------------------------------------------------------------------------
| php -S (PHP built-in server) tidak selalu mengikuti symlink.
| Route ini serve file langsung dari disk tanpa butuh symlink sama sekali.
| Pattern: /storage/{path} → storage/app/public/{path}
*/
Route::get('/storage/{path}', function (string $path) {
    // Cegah path traversal
    $path = ltrim($path, '/');
    if (str_contains($path, '..')) {
        abort(403);
    }

    $disk = Storage::disk('public');

    if (!$disk->exists($path)) {
        abort(404);
    }

    $fullPath  = $disk->path($path);
    $mimeType  = $disk->mimeType($path) ?: 'application/octet-stream';
    $size      = $disk->size($path);

    return response()->file($fullPath, [
        'Content-Type'  => $mimeType,
        'Content-Length'=> $size,
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*');
