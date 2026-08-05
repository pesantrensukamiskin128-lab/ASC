<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function student(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Student::class);
    }

    public function lecturer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Lecturer::class);
    }

    /**
     * Gabungkan permissions dari role Spatie + jabatan struktural dosen.
     * Digunakan di AuthController untuk response login/me.
     */
    public function getAllEffectivePermissions(): array
    {
        // Permissions dari role (Spatie)
        $rolePerms = $this->getAllPermissions()->pluck('name')->toArray();

        // Permissions dari jabatan struktural (jika user adalah dosen)
        $positionPerms = [];
        $lecturer = $this->lecturer;
        if ($lecturer) {
            $positionPerms = LecturerPosition::getPermissionsForLecturer($lecturer->id);
        }

        return collect(array_merge($rolePerms, $positionPerms))->unique()->values()->toArray();
    }
}
