<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalLocation extends Model
{
    protected $fillable = ['program_id', 'name', 'address', 'city', 'contact_person', 'contact_phone', 'capacity', 'supervisor_id', 'supervisor2_id'];
    public function program(): BelongsTo { return $this->belongsTo(PracticalProgram::class, 'program_id'); }
    public function supervisor(): BelongsTo { return $this->belongsTo(Lecturer::class, 'supervisor_id'); }
    public function supervisor2(): BelongsTo { return $this->belongsTo(Lecturer::class, 'supervisor2_id'); }
}
