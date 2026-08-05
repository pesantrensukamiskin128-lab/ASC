<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBankItem extends Model
{
    protected $fillable = ['question_bank_id', 'type', 'question', 'options', 'correct_answer', 'default_score', 'explanation', 'difficulty', 'tags'];
    protected $casts = ['options' => 'array', 'tags' => 'array'];
    
    public function bank(): BelongsTo { return $this->belongsTo(QuestionBank::class, 'question_bank_id'); }
}
