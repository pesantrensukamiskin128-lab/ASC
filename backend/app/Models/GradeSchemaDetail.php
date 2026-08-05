<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeSchemaDetail extends Model
{
    protected $fillable = ['grade_schema_id', 'min_score', 'max_score', 'letter', 'grade_point', 'order'];
    public function schema(): BelongsTo { return $this->belongsTo(GradeSchema::class, 'grade_schema_id'); }
}
