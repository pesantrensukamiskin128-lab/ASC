<?php

namespace App\Traits;

use App\Models\File;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HasFiles
{
    /** Semua file milik model ini */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'owner');
    }

    /** File berdasarkan collection */
    public function filesByCollection(string $collection): MorphMany
    {
        return $this->files()->where('collection', $collection);
    }

    /** Ambil satu file dari collection (misal: foto profil) */
    public function getFile(string $collection): ?File
    {
        return $this->files()->where('collection', $collection)->latest('created_at')->first();
    }

    /** Upload file baru */
    public function addFile(
        UploadedFile $uploadedFile,
        string $collection = 'default',
        string $disk = 'public',
        ?string $directory = null
    ): File {
        $directory = $directory ?? $this->getFileDirectory($collection);
        $path      = $uploadedFile->store($directory, $disk);
        $hash      = hash_file('sha256', $uploadedFile->getRealPath());

        return $this->files()->create([
            'collection'  => $collection,
            'file_name'   => $uploadedFile->getClientOriginalName(),
            'file_path'   => $path,
            'disk'        => $disk,
            'mime_type'    => $uploadedFile->getMimeType(),
            'file_size'   => $uploadedFile->getSize(),
            'file_hash'   => $hash,
            'uploaded_by'  => auth()->id(),
            'created_at'  => now(),
        ]);
    }

    /** Upload dan replace file lama (untuk single-file collection seperti foto) */
    public function setFile(
        UploadedFile $uploadedFile,
        string $collection = 'default',
        string $disk = 'public',
        ?string $directory = null
    ): File {
        // Hapus file lama di collection ini
        $this->files()->where('collection', $collection)->each(fn(File $f) => $f->deleteFile());

        return $this->addFile($uploadedFile, $collection, $disk, $directory);
    }

    /** Hapus semua file di collection tertentu */
    public function clearFiles(string $collection): void
    {
        $this->files()->where('collection', $collection)->each(fn(File $f) => $f->deleteFile());
    }

    /** Default directory berdasarkan model dan collection */
    protected function getFileDirectory(string $collection): string
    {
        $modelName = strtolower(class_basename(static::class));
        return "{$modelName}/{$collection}";
    }
}
