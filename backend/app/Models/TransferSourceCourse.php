<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TransferSourceCourse extends Model
{
    protected $fillable = ['application_id', 'course_code', 'course_name', 'credits', 'grade_letter', 'grade_numeric', 'semester_taken', 'year_taken'];
    public function application(): BelongsTo { return $this->belongsTo(TransferCreditApplication::class, 'application_id'); }
    public function conversion(): HasOne { return $this->hasOne(TransferCourseConversion::class, 'source_course_id'); }
}
