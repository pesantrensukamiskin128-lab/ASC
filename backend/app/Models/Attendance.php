<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = ['journal_id', 'student_id', 'status', 'method', 'checked_in_at', 'note'];
    protected $casts = ['checked_in_at' => 'datetime'];
    public function journal(): BelongsTo { return $this->belongsTo(LectureJournal::class, 'journal_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
