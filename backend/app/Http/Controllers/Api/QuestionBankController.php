<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\QuestionBankImport;
use App\Models\QuestionBank;
use App\Models\QuestionBankItem;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionBankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = QuestionBank::with(['course', 'creator'])
            ->withCount('items')
            ->when($request->course_id, fn($q) => $q->where('course_id', $request->course_id))
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"));

        // Dosen hanya lihat miliknya atau yang shared
        if ($user->hasRole('DOSEN') && !$user->hasRole('SUPER_ADMIN')) {
            $query->where(fn($q) => $q->where('created_by', $user->id)->orWhere('is_shared', true));
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id'   => 'required|exists:courses,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_shared'   => 'boolean',
        ]);

        $bank = QuestionBank::create(array_merge($validated, ['created_by' => auth()->id()]));
        return response()->json(['message' => 'Bank soal berhasil dibuat.', 'data' => $bank->load('course')], 201);
    }

    public function show(QuestionBank $questionBank): JsonResponse
    {
        return response()->json($questionBank->load(['course', 'creator', 'items']));
    }

    public function update(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_shared'   => 'boolean',
        ]);
        $questionBank->update($validated);
        return response()->json(['message' => 'Bank soal berhasil diupdate.', 'data' => $questionBank->fresh()]);
    }

    public function destroy(QuestionBank $questionBank): JsonResponse
    {
        $questionBank->items()->delete();
        $questionBank->delete();
        return response()->json(['message' => 'Bank soal berhasil dihapus.']);
    }

    // === ITEMS ===

    public function storeItem(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $validated = $request->validate([
            'type'           => 'required|in:PILIHAN_GANDA,BENAR_SALAH,ESAI,STUDI_KASUS,MATCHING,UPLOAD_FILE',
            'question'       => 'required|string',
            'options'        => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'default_score'  => 'nullable|numeric|min:0',
            'explanation'    => 'nullable|string',
            'difficulty'     => 'nullable|in:MUDAH,SEDANG,SULIT',
            'tags'           => 'nullable|array',
        ]);

        $item = $questionBank->items()->create($validated);
        return response()->json(['message' => 'Soal berhasil ditambahkan.', 'data' => $item], 201);
    }

    public function updateItem(Request $request, QuestionBank $questionBank, QuestionBankItem $item): JsonResponse
    {
        $validated = $request->validate([
            'type'           => 'sometimes|in:PILIHAN_GANDA,BENAR_SALAH,ESAI,STUDI_KASUS,MATCHING,UPLOAD_FILE',
            'question'       => 'sometimes|string',
            'options'        => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'default_score'  => 'nullable|numeric|min:0',
            'explanation'    => 'nullable|string',
            'difficulty'     => 'nullable|in:MUDAH,SEDANG,SULIT',
            'tags'           => 'nullable|array',
        ]);
        $item->update($validated);
        return response()->json(['message' => 'Soal berhasil diupdate.', 'data' => $item->fresh()]);
    }

    public function destroyItem(QuestionBank $questionBank, QuestionBankItem $item): JsonResponse
    {
        $item->delete();
        return response()->json(['message' => 'Soal berhasil dihapus.']);
    }

    /** Import soal dari bank ke ujian */
    public function importToExam(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $request->validate([
            'exam_id'  => 'required|exists:exams,id',
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:question_bank_items,id',
        ]);

        $exam = \App\Models\Exam::findOrFail($request->exam_id);
        $order = $exam->questions()->count();

        foreach ($request->item_ids as $itemId) {
            $bankItem = QuestionBankItem::find($itemId);
            if (!$bankItem) continue;
            $order++;
            $exam->questions()->create([
                'bank_item_id'   => $bankItem->id,
                'type'           => $bankItem->type,
                'question'       => $bankItem->question,
                'options'        => $bankItem->options,
                'correct_answer' => $bankItem->correct_answer,
                'score'          => $bankItem->default_score,
                'explanation'    => $bankItem->explanation,
                'order'          => $order,
            ]);
        }

        return response()->json(['message' => count($request->item_ids) . ' soal berhasil diimport ke ujian.']);
    }

    /** Import soal dari Excel ke bank soal */
    public function importFromExcel(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $import = new QuestionBankImport($questionBank->id);
        Excel::import($import, $request->file('file'));

        $message = "{$import->imported} soal berhasil diimport.";
        if ($import->skipped > 0) {
            $message .= " {$import->skipped} baris dilewati.";
        }

        return response()->json([
            'message'  => $message,
            'imported' => $import->imported,
            'skipped'  => $import->skipped,
            'errors'   => $import->errors,
        ]);
    }

    /** Download template Excel untuk import soal */
    public function downloadTemplate()
    {
        $filename = 'template-import-soal.xlsx';

        return Excel::download(new class implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\WithColumnWidths {
            public function array(): array
            {
                return [
                    // Baris 1: Header
                    ['tipe', 'pertanyaan', 'a', 'b', 'c', 'd', 'e', 'jawaban', 'skor', 'kesulitan', 'penjelasan', 'tags'],
                    // Baris 2-6: Contoh data
                    ['Pilihan Ganda', 'Siapakah presiden pertama Indonesia?', 'Soekarno', 'Soeharto', 'Habibie', 'Megawati', '', 'A', '1', 'Mudah', 'Soekarno adalah presiden pertama RI (1945-1967).', 'sejarah,presiden'],
                    ['Benar Salah', 'Ibu kota Indonesia adalah Jakarta.', '', '', '', '', '', 'Benar', '1', 'Mudah', 'Jakarta adalah ibu kota Indonesia.', ''],
                    ['Esai', 'Jelaskan pengertian demokrasi Pancasila!', '', '', '', '', '', '', '10', 'Sedang', '', 'demokrasi,pancasila'],
                    ['Pilihan Ganda', 'Berapa jumlah sila dalam Pancasila?', '3', '4', '5', '6', '', 'C', '1', 'Mudah', 'Pancasila memiliki 5 sila.', ''],
                    ['Studi Kasus', 'Analisis dampak korupsi terhadap pembangunan nasional!', '', '', '', '', '', '', '20', 'Sulit', '', 'korupsi,pembangunan'],
                ];
            }

            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
            {
                // Bold header
                $sheet->getStyle('A1:L1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2563EB']],
                ]);
                // Warna baris contoh bergantian
                $sheet->getStyle('A2:L6')->applyFromArray([
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F9FAFB']],
                ]);
                return [];
            }

            public function columnWidths(): array
            {
                return [
                    'A' => 18, 'B' => 50, 'C' => 20, 'D' => 20, 'E' => 20,
                    'F' => 20, 'G' => 15, 'H' => 15, 'I' => 8, 'J' => 12,
                    'K' => 40, 'L' => 25,
                ];
            }
        }, $filename);
    }
}
