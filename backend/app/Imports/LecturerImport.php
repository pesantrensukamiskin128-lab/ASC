<?php

namespace App\Imports;

use App\Models\Lecturer;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class LecturerImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, WithBatchInserts, WithChunkReading
{
    use SkipsErrors;

    private array $programCache = [];

    public function model(array $row): ?Lecturer
    {
        $name = trim($row['nama_lengkap'] ?? '');
        if (!$name) return null;

        $nidn      = trim($row['nidn'] ?? '') ?: null;
        $programId = $this->resolveProgramId($row['kode_prodi'] ?? '');

        // Buat/ambil akun user jika ada NIDN
        $userId = null;
        if ($nidn) {
            $email = !empty(trim($row['email'] ?? ''))
                ? trim($row['email'])
                : "{$nidn}@dosen.stai-aljawami.ac.id";

            $user = User::firstOrCreate(
                ['username' => $nidn],
                ['name' => $name, 'email' => $email, 'password' => Hash::make($nidn)]
            );
            if ($user->wasRecentlyCreated) {
                $user->assignRole('DOSEN');
            }
            $userId = $user->id;
        }

        $genderMap = ['laki-laki' => 'L', 'perempuan' => 'P', 'l' => 'L', 'p' => 'P'];
        $gender    = $genderMap[strtolower(trim($row['jenis_kelamin'] ?? ''))] ?? null;

        $birthDate = null;
        if (!empty($row['tanggal_lahir'])) {
            try { $birthDate = \Carbon\Carbon::parse($row['tanggal_lahir'])->format('Y-m-d'); }
            catch (\Exception) {}
        }

        $key = $nidn ? ['nidn' => $nidn] : ['full_name' => $name];

        return Lecturer::updateOrCreate($key, [
            'user_id'          => $userId,
            'study_program_id' => $programId,
            'nuptk'            => trim($row['nuptk'] ?? '') ?: null,
            'nip'              => trim($row['nip'] ?? '') ?: null,
            'degree_front'     => trim($row['gelar_depan'] ?? '') ?: null,
            'degree_back'      => trim($row['gelar_belakang'] ?? '') ?: null,
            'full_name'        => $name,
            'gender'           => $gender,
            'birth_place'      => trim($row['tempat_lahir'] ?? '') ?: null,
            'birth_date'       => $birthDate,
            'email'            => trim($row['email'] ?? '') ?: null,
            'phone'            => trim($row['no_hp'] ?? '') ?: null,
            'academic_rank'    => trim($row['jabatan_akademik'] ?? '') ?: null,
            'employment_status'=> trim($row['status_kepegawaian'] ?? 'Tetap') ?: 'Tetap',
            'status'           => strtolower($row['status'] ?? 'aktif') === 'aktif',
        ]);
    }

    public function rules(): array
    {
        return ['nama_lengkap' => 'required'];
    }

    public function batchSize(): int { return 100; }
    public function chunkSize(): int { return 200; }

    private function resolveProgramId(string $code): ?int
    {
        $code = strtoupper(trim($code));
        if (!$code) return null;

        if (!isset($this->programCache[$code])) {
            $this->programCache[$code] = StudyProgram::where('code', $code)->value('id');
        }

        return $this->programCache[$code];
    }
}
