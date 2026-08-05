<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class LecturerPosition extends Model
{
    protected $fillable = [
        'lecturer_id', 'position_code', 'position_name',
        'scope_type', 'scope_id',
        'start_date', 'end_date', 'is_active', 'decree_number',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
    ];

    /** Daftar semua jabatan yang tersedia */
    public const POSITIONS = [
        'KETUA'          => 'Ketua',
        'WK1'            => 'Wakil Ketua I',
        'WK2'            => 'Wakil Ketua II',
        'WK3'            => 'Wakil Ketua III',
        'REKTOR'         => 'Rektor',
        'WR1'            => 'Wakil Rektor I',
        'WR2'            => 'Wakil Rektor II',
        'WR3'            => 'Wakil Rektor III',
        'DEKAN'          => 'Dekan',
        'WADEK1'         => 'Wakil Dekan I',
        'WADEK2'         => 'Wakil Dekan II',
        'WADEK3'         => 'Wakil Dekan III',
        'KAPRODI'        => 'Ketua Program Studi',
        'SEKPRODI'       => 'Sekretaris Program Studi',
        'KETUA_LPM'      => 'Ketua LPM',
        'SEKRETARIS_LPM' => 'Sekretaris LPM',
        'KETUA_LP2M'     => 'Ketua LP2M',
        'SEKRETARIS_LP2M'=> 'Sekretaris LP2M',
        'DOSEN_WALI'     => 'Dosen Wali',
    ];

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    /** Ambil permissions untuk jabatan ini dari tabel position_permissions */
    public function getPermissions(): array
    {
        return DB::table('position_permissions')
            ->where('position_code', $this->position_code)
            ->pluck('permission_name')
            ->toArray();
    }

    /** Ambil semua permissions untuk seorang lecturer berdasarkan semua jabatan aktif */
    public static function getPermissionsForLecturer(int $lecturerId): array
    {
        $codes = self::where('lecturer_id', $lecturerId)
            ->where('is_active', true)
            ->pluck('position_code')
            ->toArray();

        if (empty($codes)) return [];

        return DB::table('position_permissions')
            ->whereIn('position_code', $codes)
            ->pluck('permission_name')
            ->unique()
            ->values()
            ->toArray();
    }
}
