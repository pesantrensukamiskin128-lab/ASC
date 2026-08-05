<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferDocument extends Model
{
    protected $fillable = ['application_id', 'document_type', 'name', 'file_path', 'file_url', 'is_verified', 'verified_by', 'verified_at', 'verification_notes'];
    protected $casts = ['is_verified' => 'boolean', 'verified_at' => 'datetime'];
    public function application(): BelongsTo { return $this->belongsTo(TransferCreditApplication::class, 'application_id'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
