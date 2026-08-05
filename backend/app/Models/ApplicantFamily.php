<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantFamily extends Model
{
    protected $table = 'applicant_family';
    protected $fillable = ['applicant_id', 'relation', 'name', 'nik', 'occupation', 'income', 'education', 'phone', 'address', 'is_alive'];
    protected $casts = ['is_alive' => 'boolean'];
    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
}
