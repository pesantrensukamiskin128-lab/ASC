<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferApproval extends Model
{
    protected $fillable = ['application_id', 'approver_id', 'approval_level', 'approval_role', 'status', 'notes', 'approved_at'];
    protected $casts = ['approved_at' => 'datetime'];
    public function application(): BelongsTo { return $this->belongsTo(TransferCreditApplication::class, 'application_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
}
