<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThesisDefense extends Model
{
    protected $fillable = ['thesis_id', 'type', 'defense_date', 'defense_time', 'room', 'result', 'notes', 'revision_deadline', 'revision_completed'];
    protected $casts = ['defense_date' => 'date', 'revision_deadline' => 'date', 'revision_completed' => 'boolean'];
    public function thesis(): BelongsTo { return $this->belongsTo(Thesis::class); }
    public function scores(): HasMany { return $this->hasMany(ThesisDefenseScore::class, 'defense_id'); }
}
