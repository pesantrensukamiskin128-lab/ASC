<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAnnouncement extends Model
{
    protected $fillable = ['class_id', 'user_id', 'title', 'content', 'is_published'];
    protected $casts = ['is_published' => 'boolean'];
    public function class_(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
