<?php

namespace App\Imports;

use App\Models\AcademicCalendar;
use App\Models\AcademicYear;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;

class AcademicCalendarImport implements ToModel, WithHeadingRow, SkipsOnError
{
    use SkipsErrors;

    private array $yearCache = [];
    private array $validCategories = ['Akademik', 'UTS', 'UAS', 'Libur', 'KKN', 'Wisuda', 'Lainnya'];

    public function model(array $row): ?AcademicCalendar
    {
        $title = trim($row['kegiatan'] ?? $row['title'] ?? '');
        if (!$title) return null;

        $yearName = trim($row['tahun_akademik'] ?? $row['academic_year'] ?? '');
        $yearId = $this->resolveYear($yearName);
        if (!$yearId) return null;

        $category = trim($row['kategori'] ?? $row['category'] ?? 'Lainnya');
        if (!in_array($category, $this->validCategories)) $category = 'Lainnya';

        $startDate = $this->parseDate($row['tanggal_mulai'] ?? $row['start_date'] ?? null);
        if (!$startDate) return null;

        $endDate = $this->parseDate($row['tanggal_selesai'] ?? $row['end_date'] ?? null);

        return new AcademicCalendar([
            'academic_year_id' => $yearId,
            'title' => $title,
            'description' => trim($row['deskripsi'] ?? $row['description'] ?? '') ?: null,
            'start_date' => $startDate,
            'end_date' => $endDate ?? $startDate,
            'category' => $category,
            'color' => '#3b82f6',
        ]);
    }

    private function resolveYear(string $name): ?int
    {
        if (!$name) return null;
        if (!isset($this->yearCache[$name])) {
            $this->yearCache[$name] = AcademicYear::where('name', $name)->value('id')
                ?? AcademicYear::where('name', 'like', "%{$name}%")->value('id');
        }
        return $this->yearCache[$name];
    }

    private function parseDate($value): ?string
    {
        if (!$value) return null;
        try { return \Carbon\Carbon::parse($value)->format('Y-m-d'); }
        catch (\Exception) { return null; }
    }
}
