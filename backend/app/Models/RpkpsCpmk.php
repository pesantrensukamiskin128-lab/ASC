<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RpkpsCpmk extends Model
{
    protected $fillable = ['rpkps_id', 'code', 'description', 'order'];

    public function rpkps(): BelongsTo { return $this->belongsTo(Rpkps::class); }

    public function subCpmks(): HasMany
    {
        return $this->hasMany(RpkpsSubCpmk::class, 'rpkps_cpmk_id')->orderBy('order');
    }

    /** CPL yang terhubung dengan CPMK ini */
    public function cpls(): BelongsToMany
    {
        return $this->belongsToMany(LearningOutcome::class, 'rpkps_cpmk_cpl', 'rpkps_cpmk_id', 'learning_outcome_id');
    }
}
