<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpkpsApproval extends Model
{
    protected $fillable = ['rpkps_id', 'user_id', 'action', 'note'];

    public function rpkps(): BelongsTo { return $this->belongsTo(Rpkps::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
