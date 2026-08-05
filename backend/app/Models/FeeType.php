<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeType extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'is_mandatory', 'is_recurring',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'is_mandatory' => 'boolean',
        'is_recurring' => 'boolean',
    ];

    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
