<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticalGroup extends Model
{
    protected $fillable = ['program_id', 'name', 'location_id', 'supervisor_id', 'leader_id', 'notes'];
    public function program(): BelongsTo { return $this->belongsTo(PracticalProgram::class, 'program_id'); }
    public function location(): BelongsTo { return $this->belongsTo(PracticalLocation::class, 'location_id'); }
    public function supervisor(): BelongsTo { return $this->belongsTo(Lecturer::class, 'supervisor_id'); }
    public function leader(): BelongsTo { return $this->belongsTo(PracticalParticipant::class, 'leader_id'); }
    public function participants(): HasMany { return $this->hasMany(PracticalParticipant::class, 'group_id'); }
}
