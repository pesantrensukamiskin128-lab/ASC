<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Disposition extends Model
{
    protected $fillable = ['incoming_letter_id', 'created_by', 'instruction', 'notes'];

    public function incomingLetter(): BelongsTo { return $this->belongsTo(IncomingLetter::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'disposition_recipients')
            ->withPivot('is_read', 'read_at', 'response', 'responded_at')
            ->withTimestamps();
    }
}
