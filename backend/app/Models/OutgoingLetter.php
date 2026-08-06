<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class OutgoingLetter extends Model
{
    protected $fillable = [
        'created_by', 'letter_type_id', 'letter_number', 'subject', 'recipient',
        'attachment_note', 'city', 'letter_date', 'body', 'appendix_body',
        'reviewer_id', 'signer_id', 'status', 'reviewed_at', 'signed_at',
        'sent_at', 'revision_note', 'verification_token', 'external_recipients',
    ];

    protected $casts = [
        'letter_date'  => 'date',
        'reviewed_at'  => 'datetime',
        'signed_at'    => 'datetime',
        'sent_at'      => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $letter) {
            $letter->verification_token = Str::random(32);
        });
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function letterType(): BelongsTo { return $this->belongsTo(LetterType::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function signer(): BelongsTo { return $this->belongsTo(User::class, 'signer_id'); }

    public function internalRecipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'outgoing_letter_recipients')
            ->withPivot('is_read', 'read_at')
            ->withTimestamps();
    }

    /** Generate nomor surat: 001/A/STAI-AJ/VIII/2026 */
    public function generateNumber(): string
    {
        $year = $this->letter_date->format('Y');
        $month = $this->romanMonth($this->letter_date->month);
        $code = $this->letterType->code ?? 'X';
        $institution = Institution::first();
        $orgCode = $institution?->short_name ? str_replace(' ', '-', $institution->short_name) : 'ASC';

        $count = self::whereYear('letter_date', $year)
            ->where('letter_type_id', $this->letter_type_id)
            ->whereNotNull('letter_number')
            ->count() + 1;

        return str_pad($count, 3, '0', STR_PAD_LEFT) . "/{$code}/{$orgCode}/{$month}/{$year}";
    }

    private function romanMonth(int $m): string
    {
        $roman = ['', 'I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
        return $roman[$m] ?? (string) $m;
    }
}
