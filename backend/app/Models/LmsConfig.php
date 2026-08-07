<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class LmsConfig extends Model
{
    protected $table = 'lms_config';

    protected $fillable = ['base_url', 'api_token', 'is_active', 'last_sync_at'];

    protected $casts = ['is_active' => 'boolean', 'last_sync_at' => 'datetime'];

    protected $hidden = ['api_token'];

    public function setApiTokenAttribute($value): void
    {
        $this->attributes['api_token'] = Crypt::encryptString($value);
    }

    public function getDecryptedToken(): string
    {
        return Crypt::decryptString($this->attributes['api_token']);
    }
}
