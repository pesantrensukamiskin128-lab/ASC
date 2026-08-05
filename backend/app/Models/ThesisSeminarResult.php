<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisSeminarResult extends Model
{
    protected $table = 'thesis_seminar_results';
    protected $fillable = ['thesis_id', 'seminar_type', 'seminar_date', 'room', 'result', 'notes', 'recorded_by'];
    protected $casts = ['seminar_date' => 'date'];

    public function thesis(): BelongsTo { return $this->belongsTo(Thesis::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
