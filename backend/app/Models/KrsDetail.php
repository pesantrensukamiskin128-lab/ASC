<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KrsDetail extends Model
{
    protected $table = 'krs_details';

    protected $fillable = ['krs_id', 'course_id', 'class_id', 'status'];

    public function krs(): BelongsTo { return $this->belongsTo(Krs::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function class_(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
}
