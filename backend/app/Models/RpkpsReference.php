<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpkpsReference extends Model
{
    protected $fillable = [
        'rpkps_id', 'type', 'category',
        'title', 'author', 'year', 'publisher', 'isbn_doi', 'url', 'order',
    ];

    public function rpkps(): BelongsTo { return $this->belongsTo(Rpkps::class); }
}
