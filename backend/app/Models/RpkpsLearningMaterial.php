<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpkpsLearningMaterial extends Model
{
    protected $fillable = ['rpkps_id', 'topic', 'subtopics', 'order'];

    public function rpkps(): BelongsTo { return $this->belongsTo(Rpkps::class); }
}
