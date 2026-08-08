<?php

namespace App\Http\Controllers\Api;

use App\Exports\AcademicCalendarExport;
use App\Http\Controllers\Controller;
use App\Imports\AcademicCalendarImport;
use App\Models\AcademicCalendar;
use App\Models\Institution;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AcademicCalendarController extends Controller
{
    /** Public endpoint — kalender akademik untuk halaman login (tanpa auth) */
    public function publicCalendar(): JsonResponse
    {
        $events = AcademicCalendar::with('academicYear')
            ->where('end_date', '>=', now()->subDays(7))
            ->orderBy('start_date')
            ->limit(10)
            ->get()
            ->map(fn($e) => [
                'id'         => $e->id,
                'title'      => $e->title,
                'start_date' => $e->start_date?->format('Y-m-d'),
                'end_date'   => $e->end_date?->format('Y-m-d'),
                'category'   => $e->category,
            ]);

        return response()->json($events);
    }

    public function index(Request $request): JsonResponse
    {
        $data = AcademicCalendar::with('academicYear')
            ->when($request->academic_year_id, fn($q) => $q->where('academic_year_id', $request->academic_year_id))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->orderBy('start_date')
            ->get();

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'start_date'       => 'required|date',
            'end_date'         => 'nullable|date|after_or_equal:start_date',
            'category'         => 'required|in:Akademik,UTS,UAS,Libur,KKN,Wisuda,Lainnya',
            'color'            => 'nullable|string|max:20',
        ]);

        $event = AcademicCalendar::create($validated);

        return response()->json(['message' => 'Event kalender berhasil ditambahkan.', 'data' => $event], 201);
    }

    public function show(AcademicCalendar $academicCalendar): JsonResponse
    {
        return response()->json($academicCalendar->load('academicYear'));
    }

    public function update(Request $request, AcademicCalendar $academicCalendar): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => 'sometimes|exists:academic_years,id',
            'title'            => 'sometimes|string|max:255',
            'description'      => 'nullable|string',
            'start_date'       => 'sometimes|date',
            'end_date'         => 'nullable|date',
            'category'         => 'sometimes|in:Akademik,UTS,UAS,Libur,KKN,Wisuda,Lainnya',
            'color'            => 'nullable|string|max:20',
        ]);

        $academicCalendar->update($validated);

        return response()->json(['message' => 'Event berhasil diupdate.', 'data' => $academicCalendar->fresh()]);
    }

    public function destroy(AcademicCalendar $academicCalendar): JsonResponse
    {
        $academicCalendar->delete();
        return response()->json(['message' => 'Event berhasil dihapus.']);
    }

    /** Export ke Excel */
    public function export(Request $request)
    {
        $filename = 'kalender-akademik-' . now()->format('Ymd') . '.xlsx';
        return Excel::download(new AcademicCalendarExport($request->academic_year_id), $filename);
    }

    /** Import dari Excel */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        $import = new AcademicCalendarImport();
        Excel::import($import, $request->file('file'));

        $errors = collect($import->errors())->map(fn($e) => $e->getMessage())->values();

        return response()->json([
            'message' => 'Import selesai.' . ($errors->count() ? " {$errors->count()} baris dilewati." : ''),
            'errors' => $errors,
        ]);
    }

    /** Download PDF kalender akademik dengan kop surat */
    public function downloadPdf(Request $request)
    {
        $events = AcademicCalendar::with('academicYear')
            ->when($request->academic_year_id, fn($q) => $q->where('academic_year_id', $request->academic_year_id))
            ->orderBy('start_date')
            ->get();

        $institution = Institution::first();
        $letterheadUrl = null;
        if ($institution?->letterhead_path) {
            $letterheadUrl = storage_path('app/public/' . $institution->letterhead_path);
        }

        // Ambil nama Wakil Ketua I dari jabatan struktural (sertakan gelar)
        $wk1Position = \App\Models\LecturerPosition::where('position_code', 'WK1')
            ->where('is_active', true)
            ->first();
        $wk1Lecturer = $wk1Position?->lecturer;
        $wk1Name = '___________________';
        $wk1Nidn = '-';
        if ($wk1Lecturer) {
            $front = $wk1Lecturer->degree_front ? $wk1Lecturer->degree_front . ' ' : '';
            $back  = $wk1Lecturer->degree_back ? ', ' . $wk1Lecturer->degree_back : '';
            $wk1Name = $front . $wk1Lecturer->full_name . $back;
            $wk1Nidn = $wk1Lecturer->nidn ?? '-';
        }

        // Generate verification URL & QR via helper
        $academicYearId = $request->academic_year_id ?? 'all';
        $verifyUrl = rtrim(config('app.frontend_url'), '/')
            . '/verify/academic-calendar/' . $academicYearId;
        $qrSignature = \App\Helpers\QrCodeHelper::generate($verifyUrl, 120);
        $qrFooter = \App\Helpers\QrCodeHelper::generateWithLogo($verifyUrl, 160);

        $pdf = Pdf::loadView('pdf.academic-calendar', [
            'events'        => $events,
            'institution'   => $institution,
            'letterheadUrl' => $letterheadUrl,
            'academicYear'  => $request->academic_year_id
                ? \App\Models\AcademicYear::find($request->academic_year_id)?->name
                : 'Semua Tahun Akademik',
            'wk1Name'       => $wk1Name,
            'wk1Nidn'       => $wk1Nidn,
            'qrSignature'   => $qrSignature,
            'qrFooter'      => $qrFooter,
            'verifyUrl'     => $verifyUrl,
        ])->setPaper('a4', 'portrait')
          ->setOption('isRemoteEnabled', true);

        return $pdf->download('kalender-akademik.pdf');
    }
}
