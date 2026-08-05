<?php

namespace App\Imports;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class StaffImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, WithBatchInserts, WithChunkReading
{
    use SkipsErrors;

    public function model(array $row): ?Staff
    {
        $name = trim($row['nama_lengkap'] ?? '');
        if (!$name) return null;

        $nip    = trim($row['nip'] ?? '') ?: null;
        $userId = null;

        if ($nip) {
            $email = !empty(trim($row['email'] ?? ''))
                ? trim($row['email'])
                : "{$nip}@staff.jawami.ac.id";

            $user = User::firstOrCreate(
                ['username' => $nip],
                ['name' => $name, 'email' => $email, 'password' => Hash::make($nip)]
            );
            $userId = $user->id;
        }

        $genderMap = ['laki-laki' => 'L', 'perempuan' => 'P', 'l' => 'L', 'p' => 'P'];
        $genderRaw = strtolower(trim($row['jenis_kelamin'] ?? ''));
        $gender    = $genderMap[$genderRaw] ?? null;

        $birthDate = null;
        if (!empty($row['tanggal_lahir'])) {
            try { $birthDate = \Carbon\Carbon::parse($row['tanggal_lahir'])->format('Y-m-d'); }
            catch (\Exception) {}
        }

        return Staff::updateOrCreate(
            ['nip' => $nip ?? 'TEMP-' . uniqid()],
            [
                'user_id'           => $userId,
                'name'              => $name,
                'gender'            => $gender,
                'birth_place'       => trim($row['tempat_lahir'] ?? '') ?: null,
                'birth_date'        => $birthDate,
                'email'             => trim($row['email'] ?? '') ?: null,
                'phone'             => trim($row['no_hp'] ?? '') ?: null,
                'position'          => trim($row['jabatan'] ?? '') ?: null,
                'department'        => trim($row['unit_bagian'] ?? '') ?: null,
                'employment_status' => trim($row['status_kepegawaian'] ?? 'Tetap') ?: 'Tetap',
                'status'            => strtolower($row['status'] ?? 'aktif') === 'aktif',
            ]
        );
    }

    public function rules(): array
    {
        return ['nama_lengkap' => 'required'];
    }

    public function batchSize(): int { return 100; }
    public function chunkSize(): int { return 200; }
}
