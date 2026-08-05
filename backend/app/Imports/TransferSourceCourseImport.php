<?php

namespace App\Imports;

use App\Models\TransferSourceCourse;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class TransferSourceCourseImport implements ToModel, WithHeadingRow, WithValidation
{
    private int $applicationId;

    public function __construct(int $applicationId)
    {
        $this->applicationId = $applicationId;
    }

    public function model(array $row)
    {
        return new TransferSourceCourse([
            'application_id' => $this->applicationId,
            'course_code'    => $row['kode'] ?? $row['course_code'] ?? null,
            'course_name'    => $row['mata_kuliah'] ?? $row['course_name'] ?? $row['nama'],
            'credits'        => $row['sks'] ?? $row['credits'],
            'grade_letter'   => $row['nilai'] ?? $row['grade'] ?? $row['grade_letter'] ?? null,
            'grade_numeric'  => $row['bobot'] ?? $row['grade_point'] ?? $row['grade_numeric'] ?? null,
            'semester_taken' => $row['semester'] ?? $row['semester_taken'] ?? null,
            'year_taken'     => $row['tahun'] ?? $row['year'] ?? $row['year_taken'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'mata_kuliah' => 'required_without_all:course_name,nama',
            'sks'         => 'required_without:credits',
        ];
    }
}
