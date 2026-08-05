<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpkpsSubCpmk extends Model
{
    protected $table = 'rpkps_sub_cpmks';
    protected $fillable = ['rpkps_cpmk_id', 'code', 'description', 'order'];

    public function cpmk(): BelongsTo { return $this->belongsTo(RpkpsCpmk::class, 'rpkps_cpmk_id'); }
}
