<?php

namespace App\Imports;

use App\Models\Lecturer;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StudentImport implements SkipsOnError, ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    use SkipsErrors;

    private array $programCache = [];

    private array $advisorCache = [];

    public function model(array $row): ?Student
    {
        $nim = trim($row['nim'] ?? '');
        $name = trim($row['nama_lengkap'] ?? '');
        if (! $nim || ! $name) {
            return null;
        }

        $programId = $this->resolveProgramId($row['kode_prodi'] ?? '');
        if (! $programId) {
            return null;
        }

        // Buat akun user otomatis
        $email = ! empty(trim($row['email'] ?? ''))
            ? trim($row['email'])
            : "{$nim}@student.stai-aljawami.ac.id";

        $user = User::firstOrCreate(
            ['username' => $nim],
            ['name' => $name, 'email' => $email, 'password' => Hash::make($nim)]
        );
        if ($user->wasRecentlyCreated) {
            $user->assignRole('MAHASISWA');
        }

        $genderMap = ['laki-laki' => 'L', 'perempuan' => 'P', 'l' => 'L', 'p' => 'P'];
        $genderRaw = strtolower(trim($row['jenis_kelamin'] ?? ''));
        $gender = $genderMap[$genderRaw] ?? null;

        $birthDate = null;
        if (! empty($row['tanggal_lahir'])) {
            try {
                $birthDate = Carbon::parse($row['tanggal_lahir'])->format('Y-m-d');
            } catch (\Exception) {
            }
        }

        $status = in_array($row['status'] ?? '', Student::STATUSES, true) ? $row['status'] : 'Aktif';

        return Student::updateOrCreate(
            ['nim' => $nim],
            [
                'user_id' => $user->id,
                'study_program_id' => $programId,
                'advisor_id' => $this->resolveAdvisorId($row['dosen_wali'] ?? ''),
                'name' => $name,
                'gender' => $gender,
                'birth_place' => trim($row['tempat_lahir'] ?? '') ?: null,
                'birth_date' => $birthDate,
                'email' => $email,
                'phone' => trim($row['no_hp'] ?? '') ?: null,
                'origin_school' => trim($row['asal_sekolah'] ?? '') ?: null,
                'entry_year' => ! empty($row['tahun_masuk']) ? (int) $row['tahun_masuk'] : null,
                'status' => $status,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'nim' => 'required',
            'nama_lengkap' => 'required',
            'kode_prodi' => 'required',
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 200;
    }

    private function resolveProgramId(string $code): ?int
    {
        $code = strtoupper(trim($code));
        if (! $code) {
            return null;
        }

        if (! isset($this->programCache[$code])) {
            $this->programCache[$code] = StudyProgram::where('code', $code)->value('id');
        }

        return $this->programCache[$code];
    }

    private function resolveAdvisorId(string $nidn): ?int
    {
        $nidn = trim($nidn);
        if (! $nidn) {
            return null;
        }

        if (! isset($this->advisorCache[$nidn])) {
            $this->advisorCache[$nidn] = Lecturer::where('nidn', $nidn)->value('id');
        }

        return $this->advisorCache[$nidn];
    }
}
