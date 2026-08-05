<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisDefenseScore extends Model
{
    protected $fillable = ['defense_id', 'examiner_id', 'component', 'score', 'notes'];
    public function defense(): BelongsTo { return $this->belongsTo(ThesisDefense::class, 'defense_id'); }
    public function examiner(): BelongsTo { return $this->belongsTo(Lecturer::class, 'examiner_id'); }
}
