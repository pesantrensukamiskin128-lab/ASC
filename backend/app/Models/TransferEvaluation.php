<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferEvaluation extends Model
{
    protected $fillable = ['application_id', 'evaluator_id', 'evaluation_date', 'total_source_credits', 'total_recognized_credits', 'total_rejected_credits', 'notes', 'recommendation'];
    protected $casts = ['evaluation_date' => 'date'];
    public function application(): BelongsTo { return $this->belongsTo(TransferCreditApplication::class, 'application_id'); }
    public function evaluator(): BelongsTo { return $this->belongsTo(User::class, 'evaluator_id'); }
}
