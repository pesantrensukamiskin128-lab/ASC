<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassMember extends Model
{
    protected $fillable = ['class_id', 'student_id'];

    public function class_(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
