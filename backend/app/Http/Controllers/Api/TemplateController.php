<?php

namespace App\Http\Controllers\Api;

use App\Exports\TemplateExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TemplateController extends Controller
{
    private const ALLOWED = ['courses', 'lecturers', 'students', 'staff'];

    public function download(Request $request, string $type)
    {
        if (!in_array($type, self::ALLOWED)) {
            return response()->json(['message' => 'Tipe template tidak valid.'], 422);
        }

        $names = [
            'courses'   => 'template-mata-kuliah',
            'lecturers' => 'template-dosen',
            'students'  => 'template-mahasiswa',
            'staff'     => 'template-tenaga-kependidikan',
        ];

        return Excel::download(new TemplateExport($type), $names[$type] . '.xlsx');
    }
}
