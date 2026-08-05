<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discussion extends Model
{
    protected $fillable = ['class_id', 'user_id', 'title', 'content', 'is_pinned', 'is_locked'];
    protected $casts = ['is_pinned' => 'boolean', 'is_locked' => 'boolean'];
    public function class_(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function replies(): HasMany { return $this->hasMany(DiscussionReply::class); }
}
