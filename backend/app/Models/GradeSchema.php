<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeSchema extends Model
{
    protected $fillable = ['name', 'is_default'];
    protected $casts = ['is_default' => 'boolean'];
    public function details(): HasMany { return $this->hasMany(GradeSchemaDetail::class)->orderBy('order'); }
    
    public function convertScore(float $score): ?array
    {
        $detail = $this->details()->where('min_score', '<=', $score)->where('max_score', '>=', $score)->first();
        return $detail ? ['letter' => $detail->letter, 'grade_point' => $detail->grade_point] : null;
    }
}
