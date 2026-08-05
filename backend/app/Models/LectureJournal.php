<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LectureJournal extends Model
{
    protected $fillable = ['class_id', 'meeting_number', 'meeting_date', 'topic', 'description', 'learning_activity', 'materials_note', 'status', 'lecturer_id', 'latitude', 'longitude', 'photo_path'];
    protected $casts = ['meeting_date' => 'date'];
    public function class_(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
    public function lecturer(): BelongsTo { return $this->belongsTo(Lecturer::class); }
    public function attendances(): HasMany { return $this->hasMany(Attendance::class, 'journal_id'); }
    public function materials(): HasMany { return $this->hasMany(LectureMaterial::class, 'journal_id'); }
}
