<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RpkpsAssessment extends Model
{
    protected $fillable = ['rpkps_id', 'name', 'weight', 'description', 'order'];

    public function rpkps(): BelongsTo { return $this->belongsTo(Rpkps::class); }

    public function rubrics(): HasMany { return $this->hasMany(RpkpsRubric::class); }
}
