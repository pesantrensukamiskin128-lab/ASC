<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmbExamScore extends Model
{
    protected $fillable = ['registrant_id', 'exam_type_id', 'score', 'note', 'scored_by'];

    public function registrant(): BelongsTo
    {
        return $this->belongsTo(PmbRegistrant::class, 'registrant_id');
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(PmbExamType::class, 'exam_type_id');
    }

    public function scoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
