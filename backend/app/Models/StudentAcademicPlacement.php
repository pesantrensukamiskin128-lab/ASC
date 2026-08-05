<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAcademicPlacement extends Model
{
    protected $fillable = ['student_id', 'application_id', 'recommended_semester', 'approved_semester', 'approved_by', 'notes'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function application(): BelongsTo { return $this->belongsTo(TransferCreditApplication::class, 'application_id'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
