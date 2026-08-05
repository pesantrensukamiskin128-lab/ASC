<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GraduationDocument extends Model
{
    protected $fillable = ['registration_id', 'type', 'document_number', 'file_path', 'issued_date', 'notes'];
    protected $casts = ['issued_date' => 'date'];
    public function registration(): BelongsTo { return $this->belongsTo(GraduationRegistration::class, 'registration_id'); }
}
