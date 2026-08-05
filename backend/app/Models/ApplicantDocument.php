<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantDocument extends Model
{
    protected $fillable = ['applicant_id', 'type', 'name', 'file_path', 'file_url', 'is_verified', 'verified_by', 'verified_at'];
    protected $casts = ['is_verified' => 'boolean', 'verified_at' => 'datetime'];
    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
}
