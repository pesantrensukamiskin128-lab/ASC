<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomingLetter extends Model
{
    protected $fillable = [
        'created_by', 'letter_number', 'sender', 'subject',
        'letter_date', 'received_date', 'notes', 'file_path', 'status',
    ];

    protected $casts = [
        'letter_date'   => 'date',
        'received_date' => 'date',
    ];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function dispositions(): HasMany { return $this->hasMany(Disposition::class, 'incoming_letter_id'); }
}
