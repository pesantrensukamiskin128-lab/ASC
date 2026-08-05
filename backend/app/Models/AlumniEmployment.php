<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumniEmployment extends Model
{
    protected $fillable = ['alumni_id', 'company_name', 'position', 'industry', 'city', 'start_date', 'end_date', 'is_current', 'salary_range', 'description'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean'];
    public function alumni(): BelongsTo { return $this->belongsTo(Alumni::class); }
}
