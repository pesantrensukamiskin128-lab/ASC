<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidanceNote extends Model
{
    protected $fillable = ['session_id', 'user_id', 'content', 'attachment_path', 'is_private'];

    protected $casts = ['is_private' => 'boolean'];

    public function session(): BelongsTo { return $this->belongsTo(GuidanceSession::class, 'session_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
