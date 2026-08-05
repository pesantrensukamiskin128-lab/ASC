<?php

namespace App\Models;

use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model
{
    use HasFiles;
    protected $table = 'staff';

    protected $fillable = [
        'user_id', 'nip', 'name', 'gender', 'birth_place', 'birth_date',
        'email', 'phone', 'address', 'position', 'department',
        'employment_status', 'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'status'     => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
