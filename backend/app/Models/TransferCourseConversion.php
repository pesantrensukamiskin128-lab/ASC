<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferCourseConversion extends Model
{
    protected $fillable = [
        'application_id', 'source_course_id', 'target_course_id',
        'source_credits', 'target_credits', 'recognized_credits',
        'source_grade', 'source_grade_point', 'converted_grade', 'converted_grade_point',
        'conversion_type', 'status', 'notes',
    ];
    public function application(): BelongsTo { return $this->belongsTo(TransferCreditApplication::class, 'application_id'); }
    public function sourceCourse(): BelongsTo { return $this->belongsTo(TransferSourceCourse::class, 'source_course_id'); }
    public function targetCourse(): BelongsTo { return $this->belongsTo(Course::class, 'target_course_id'); }
}
