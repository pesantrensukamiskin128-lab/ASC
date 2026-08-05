<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumniFurtherStudy extends Model
{
    protected $fillable = ['alumni_id', 'institution', 'program', 'degree', 'entry_year', 'graduation_year', 'is_current'];
    protected $casts = ['is_current' => 'boolean'];
    public function alumni(): BelongsTo { return $this->belongsTo(Alumni::class); }
}
