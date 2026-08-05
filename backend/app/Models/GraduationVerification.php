<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduationVerification extends Model
{
    protected $fillable = ['registration_id', 'requirement', 'category', 'is_fulfilled', 'verified_by', 'verified_at', 'notes'];
    protected $casts = ['is_fulfilled' => 'boolean', 'verified_at' => 'datetime'];
    public function registration(): BelongsTo { return $this->belongsTo(GraduationRegistration::class, 'registration_id'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
