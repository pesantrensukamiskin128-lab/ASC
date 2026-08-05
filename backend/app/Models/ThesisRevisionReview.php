<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisRevisionReview extends Model
{
    protected $table = 'thesis_revision_reviews';
    protected $fillable = ['thesis_id', 'examiner_id', 'type', 'score', 'notes', 'revision_link', 'revision_result', 'reviewed_at'];
    protected $casts = ['reviewed_at' => 'datetime'];

    public function thesis(): BelongsTo { return $this->belongsTo(Thesis::class); }
    public function examiner(): BelongsTo { return $this->belongsTo(Lecturer::class, 'examiner_id'); }
}
