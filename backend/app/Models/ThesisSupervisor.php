<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisSupervisor extends Model
{
    protected $fillable = ['thesis_id', 'lecturer_id', 'role', 'is_approved', 'approved_at'];
    protected $casts = ['is_approved' => 'boolean', 'approved_at' => 'date'];
    public function thesis(): BelongsTo { return $this->belongsTo(Thesis::class); }
    public function lecturer(): BelongsTo { return $this->belongsTo(Lecturer::class); }
}
