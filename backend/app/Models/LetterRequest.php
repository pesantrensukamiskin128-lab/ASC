<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterRequest extends Model
{
    protected $fillable = [
        'requested_by', 'letter_type_id', 'purpose', 'description',
        'status', 'processed_by', 'admin_note', 'outgoing_letter_id',
    ];

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
    public function letterType(): BelongsTo { return $this->belongsTo(LetterType::class); }
    public function outgoingLetter(): BelongsTo { return $this->belongsTo(OutgoingLetter::class); }
}
