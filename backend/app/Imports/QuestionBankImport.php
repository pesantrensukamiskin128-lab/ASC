<?php

namespace App\Imports;

use App\Models\QuestionBankItem;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Collection;

class QuestionBankImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $imported = 0;
    public int $skipped  = 0;
    public array $errors = [];

    private int $bankId;

    // Mapping tipe soal dari bahasa Indonesia ke enum
    private array $typeMap = [
        'pilihan ganda'  => 'PILIHAN_GANDA',
        'pg'             => 'PILIHAN_GANDA',
        'benar salah'    => 'BENAR_SALAH',
        'benar/salah'    => 'BENAR_SALAH',
        'esai'           => 'ESAI',
        'essay'          => 'ESAI',
        'studi kasus'    => 'STUDI_KASUS',
        'studi_kasus'    => 'STUDI_KASUS',
        'upload file'    => 'UPLOAD_FILE',
        'upload_file'    => 'UPLOAD_FILE',
        // English
        'multiple_choice'  => 'PILIHAN_GANDA',
        'multiple choice'  => 'PILIHAN_GANDA',
        'true_false'       => 'BENAR_SALAH',
        'true false'       => 'BENAR_SALAH',
        'pilihan_ganda'    => 'PILIHAN_GANDA',
    ];

    private array $diffMap = [
        'mudah'  => 'MUDAH',
        'easy'   => 'MUDAH',
        'sedang' => 'SEDANG',
        'medium' => 'SEDANG',
        'sulit'  => 'SULIT',
        'hard'   => 'SULIT',
        'susah'  => 'SULIT',
    ];

    public function __construct(int $bankId)
    {
        $this->bankId = $bankId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // +2 karena row 1 = header

            $question = trim($row['pertanyaan'] ?? $row['soal'] ?? $row['question'] ?? '');
            if (!$question) {
                $this->skipped++;
                continue;
            }

            // Resolve tipe
            $typeRaw = strtolower(trim($row['tipe'] ?? $row['type'] ?? 'pilihan ganda'));
            $type = $this->typeMap[$typeRaw] ?? 'PILIHAN_GANDA';

            // Resolve difficulty
            $diffRaw = strtolower(trim($row['kesulitan'] ?? $row['difficulty'] ?? 'sedang'));
            $difficulty = $this->diffMap[$diffRaw] ?? 'SEDANG';

            // Skor
            $score = is_numeric($row['skor'] ?? $row['score'] ?? null)
                ? (float) ($row['skor'] ?? $row['score'])
                : 1;

            // Pilihan jawaban — A, B, C, D, E
            $options = null;
            if ($type === 'PILIHAN_GANDA') {
                $opts = [];
                foreach (['a', 'b', 'c', 'd', 'e'] as $col) {
                    $val = trim($row[$col] ?? $row['pilihan_' . $col] ?? '');
                    if ($val !== '') $opts[] = $val;
                }
                if (count($opts) >= 2) $options = $opts;
            } elseif ($type === 'BENAR_SALAH') {
                $options = ['Benar', 'Salah'];
            }

            // Jawaban benar
            $correctRaw = trim($row['jawaban'] ?? $row['jawaban_benar'] ?? $row['correct_answer'] ?? '');
            $correctAnswer = null;
            if ($correctRaw !== '') {
                // Jika hanya huruf (A/B/C/D), resolve ke teks pilihan
                if ($type === 'PILIHAN_GANDA' && $options && strlen($correctRaw) === 1) {
                    $idx = ord(strtoupper($correctRaw)) - ord('A');
                    $correctAnswer = $options[$idx] ?? $correctRaw;
                } else {
                    $correctAnswer = $correctRaw;
                }
            }

            // Tags
            $tagsRaw = trim($row['tags'] ?? '');
            $tags = $tagsRaw ? array_map('trim', explode(',', $tagsRaw)) : [];
            $tags = array_values(array_filter($tags));

            // Penjelasan
            $explanation = trim($row['penjelasan'] ?? $row['explanation'] ?? '') ?: null;

            try {
                QuestionBankItem::create([
                    'question_bank_id' => $this->bankId,
                    'type'             => $type,
                    'question'         => $question,
                    'options'          => $options,
                    'correct_answer'   => $correctAnswer,
                    'default_score'    => $score,
                    'explanation'      => $explanation,
                    'difficulty'       => $difficulty,
                    'tags'             => $tags ?: null,
                ]);
                $this->imported++;
            } catch (\Throwable $e) {
                $this->skipped++;
                $this->errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }
    }
}
