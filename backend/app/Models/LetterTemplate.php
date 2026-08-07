<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterTemplate extends Model
{
    protected $fillable = [
        'created_by', 'name', 'description', 'letter_type_id',
        'subject', 'recipient', 'attachment_note', 'city', 'body', 'appendix_body', 'is_shared',
    ];

    protected $casts = ['is_shared' => 'boolean'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function letterType(): BelongsTo { return $this->belongsTo(LetterType::class); }
}
