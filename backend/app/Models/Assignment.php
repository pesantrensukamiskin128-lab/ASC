<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = ['class_id', 'journal_id', 'title', 'description', 'instructions', 'type', 'due_date', 'max_score', 'is_published', 'allow_late'];
    protected $casts = ['due_date' => 'datetime', 'is_published' => 'boolean', 'allow_late' => 'boolean'];
    public function class_(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
    public function submissions(): HasMany { return $this->hasMany(AssignmentSubmission::class); }
}
