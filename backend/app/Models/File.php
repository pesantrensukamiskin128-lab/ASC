<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'owner_type', 'owner_id', 'collection',
        'file_name', 'file_path', 'disk',
        'mime_type', 'file_size', 'file_hash',
        'metadata', 'uploaded_by', 'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'file_size'  => 'integer',
        'created_at' => 'datetime',
    ];

    protected $appends = ['url'];

    // === Relations ===

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // === Accessors ===

    public function getUrlAttribute(): ?string
    {
        if (!$this->file_path) return null;
        return Storage::disk($this->disk)->url($this->file_path);
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    // === Methods ===

    /** Hapus file dari storage */
    public function deleteFile(): bool
    {
        if ($this->file_path && Storage::disk($this->disk)->exists($this->file_path)) {
            Storage::disk($this->disk)->delete($this->file_path);
        }
        return $this->delete();
    }
}
