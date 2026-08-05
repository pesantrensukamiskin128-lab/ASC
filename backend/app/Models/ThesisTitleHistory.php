<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisTitleHistory extends Model
{
    protected $fillable = ['thesis_id', 'old_title', 'new_title', 'reason', 'approved_by'];
    public function thesis(): BelongsTo { return $this->belongsTo(Thesis::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
