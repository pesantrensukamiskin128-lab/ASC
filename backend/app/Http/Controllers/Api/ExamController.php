<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $isMahasiswa = $user->hasRole('MAHASISWA');

        $data = Exam::with(['class_.course', 'class_.semester'])
            ->when($request->class_id, fn($q) => $q->where('class_id', $request->class_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            // Mahasiswa hanya lihat ujian yang sudah PUBLISHED, ONGOING, atau FINISHED
            ->when($isMahasiswa, fn($q) => $q->whereIn('status', ['PUBLISHED', 'ONGOING', 'FINISHED']))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);
        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id'         => 'required|exists:classes,id',
            'title'            => 'required|string|max:255',
            'type'             => 'required|in:UTS,UAS,QUIZ,TUGAS_BESAR',
            'description'      => 'nullable|string',
            'start_time'       => 'nullable|date',
            'end_time'         => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'shuffle_questions'=> 'boolean',
            'shuffle_options'  => 'boolean',
            'show_score'       => 'boolean',
            'is_online'        => 'boolean',
            'room_id'          => 'nullable|exists:rooms,id',
            'supervisor_id'    => 'nullable|exists:lecturers,id',
        ]);

        $validated['token'] = strtoupper(Str::random(6));
        $exam = Exam::create($validated);

        return response()->json(['message' => 'Ujian berhasil dibuat.', 'data' => $exam], 201);
    }

    public function show(Exam $exam): JsonResponse
    {
        return response()->json($exam->load(['class_.course', 'questions', 'room', 'supervisor']));
    }

    public function update(Request $request, Exam $exam): JsonResponse
    {
        $validated = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'description'      => 'nullable|string',
            'start_time'       => 'nullable|date',
            'end_time'         => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'shuffle_questions'=> 'boolean',
            'shuffle_options'  => 'boolean',
            'show_score'       => 'boolean',
            'is_online'        => 'boolean',
            'status'           => 'nullable|in:DRAFT,PUBLISHED,ONGOING,FINISHED',
            'room_id'          => 'nullable|exists:rooms,id',
            'supervisor_id'    => 'nullable|exists:lecturers,id',
        ]);
        $exam->update($validated);
        return response()->json(['message' => 'Ujian berhasil diupdate.', 'data' => $exam->fresh()]);
    }

    public function destroy(Exam $exam): JsonResponse
    {
        if ($exam->sessions()->count() > 0) {
            return response()->json(['message' => 'Tidak bisa hapus ujian yang sudah ada peserta.'], 422);
        }
        $exam->delete();
        return response()->json(['message' => 'Ujian berhasil dihapus.']);
    }

    // === SOAL ===
    public function storeQuestion(Request $request, Exam $exam): JsonResponse
    {
        $validated = $request->validate([
            'type'           => 'required|in:PILIHAN_GANDA,BENAR_SALAH,ESAI,STUDI_KASUS,MATCHING,UPLOAD_FILE',
            'question'       => 'required|string',
            'options'        => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'score'          => 'required|numeric|min:0',
            'explanation'    => 'nullable|string',
        ]);
        $validated['order'] = $exam->questions()->count() + 1;

        $q = $exam->questions()->create($validated);
        return response()->json(['message' => 'Soal berhasil ditambahkan.', 'data' => $q], 201);
    }

    public function updateQuestion(Request $request, Exam $exam, ExamQuestion $question): JsonResponse
    {
        $validated = $request->validate([
            'question'       => 'sometimes|string',
            'options'        => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'score'          => 'sometimes|numeric|min:0',
            'explanation'    => 'nullable|string',
            'order'          => 'nullable|integer',
        ]);
        $question->update($validated);
        return response()->json(['message' => 'Soal berhasil diupdate.', 'data' => $question->fresh()]);
    }

    public function destroyQuestion(Exam $exam, ExamQuestion $question): JsonResponse
    {
        $question->delete();
        return response()->json(['message' => 'Soal berhasil dihapus.']);
    }

    // === MAHASISWA: MULAI & SUBMIT UJIAN ===
    public function startExam(Request $request, Exam $exam): JsonResponse
    {
        $request->validate(['token' => 'required|string']);
        if (strtoupper($request->token) !== $exam->token) {
            return response()->json(['message' => 'Token ujian salah.'], 422);
        }
        if ($exam->status !== 'PUBLISHED' && $exam->status !== 'ONGOING') {
            return response()->json(['message' => 'Ujian belum dibuka.'], 422);
        }

        $student = auth()->user()->student;
        if (!$student) return response()->json(['message' => 'Bukan mahasiswa.'], 403);

        // Cek apakah sudah submit
        $existing = ExamSession::where('exam_id', $exam->id)->where('student_id', $student->id)->first();
        if ($existing && $existing->status === 'SUBMITTED') {
            return response()->json(['message' => 'Anda sudah mengumpulkan jawaban untuk ujian ini.'], 422);
        }

        $session = ExamSession::firstOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $student->id],
            ['started_at' => now(), 'status' => 'IN_PROGRESS']
        );

        // Ambil soal (random jika diaktifkan)
        $questions = $exam->questions()->get();
        if ($exam->shuffle_questions) $questions = $questions->shuffle();

        return response()->json([
            'session'   => $session,
            'questions' => $questions->map(function ($q) use ($exam) {
                $data = ['id' => $q->id, 'type' => $q->type, 'question' => $q->question, 'score' => $q->score, 'order' => $q->order];
                if ($q->options) {
                    $opts = $q->options;
                    if ($exam->shuffle_options) shuffle($opts);
                    $data['options'] = $opts;
                }
                return $data;
            }),
            'duration_minutes' => $exam->duration_minutes,
            'exam_title'       => $exam->title,
        ]);
    }

    public function submitAnswer(Request $request, Exam $exam): JsonResponse
    {
        $request->validate([
            'answers'               => 'required|array',
            'answers.*.question_id' => 'required|exists:exam_questions,id',
            'answers.*.answer'      => 'nullable|string',
        ]);

        $student = auth()->user()->student;
        $session = ExamSession::where('exam_id', $exam->id)->where('student_id', $student->id)->first();
        if (!$session) return response()->json(['message' => 'Sesi ujian tidak ditemukan.'], 422);

        $totalScore = 0;
        DB::transaction(function () use ($request, $exam, $student, &$totalScore) {
            foreach ($request->answers as $a) {
                $question = ExamQuestion::find($a['question_id']);
                $isCorrect = null;
                $score = null;

                // Auto-grade untuk PG dan B/S
                if (in_array($question->type, ['PILIHAN_GANDA', 'BENAR_SALAH']) && $question->correct_answer) {
                    $isCorrect = strtolower(trim($a['answer'] ?? '')) === strtolower(trim($question->correct_answer));
                    $score = $isCorrect ? $question->score : 0;
                    $totalScore += $score;
                }

                ExamAnswer::updateOrCreate(
                    ['exam_id' => $exam->id, 'student_id' => $student->id, 'question_id' => $a['question_id']],
                    ['answer' => $a['answer'] ?? null, 'is_correct' => $isCorrect, 'score' => $score]
                );
            }
        });

        $session->update(['finished_at' => now(), 'total_score' => $totalScore, 'status' => 'SUBMITTED']);

        return response()->json(['message' => 'Jawaban berhasil disimpan.', 'total_score' => $totalScore]);
    }

    public function logTabSwitch(Request $request, Exam $exam): JsonResponse
    {
        $student = auth()->user()->student;
        $session = ExamSession::where('exam_id', $exam->id)->where('student_id', $student->id)->first();
        if ($session) $session->increment('tab_switches');
        return response()->json(['message' => 'Logged.']);
    }

    /** Mahasiswa cek hasil ujian sendiri */
    public function myResult(Exam $exam): JsonResponse
    {
        $student = auth()->user()->student;
        if (!$student) return response()->json(['message' => 'Bukan mahasiswa.'], 403);

        $session = ExamSession::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->first();

        if (!$session) {
            return response()->json(['status' => 'NOT_STARTED', 'session' => null, 'answers' => []]);
        }

        // Ambil jawaban beserta info soal (tanpa correct_answer jika show_score off)
        $answers = ExamAnswer::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->with('question')
            ->get()
            ->map(function ($a) use ($exam) {
                $data = [
                    'question_id'    => $a->question_id,
                    'question_text'  => $a->question?->question,
                    'question_type'  => $a->question?->type,
                    'options'        => $a->question?->options,
                    'student_answer' => $a->answer,
                    'score'          => $a->score,
                    'max_score'      => $a->question?->score,
                    'is_correct'     => null,
                    'correct_answer' => null,
                ];
                // Tampilkan jawaban benar & status hanya jika show_score aktif
                if ($exam->show_score) {
                    $data['is_correct']     = $a->is_correct;
                    $data['correct_answer'] = $a->question?->correct_answer;
                    $data['explanation']    = $a->question?->explanation;
                }
                return $data;
            });

        $maxScore = $exam->questions()->sum('score');

        return response()->json([
            'status'      => $session->status,
            'session'     => $session,
            'total_score' => $session->total_score,
            'max_score'   => $maxScore,
            'show_score'  => $exam->show_score,
            'answers'     => $answers,
            'tab_switches'=> $session->tab_switches,
        ]);
    }

    // === DOSEN: LIHAT HASIL UJIAN ===

    /** Daftar peserta + skor ringkasan */
    public function results(Exam $exam): JsonResponse
    {
        $sessions = ExamSession::where('exam_id', $exam->id)
            ->with('student:id,nim,name')
            ->orderByDesc('total_score')
            ->get()
            ->map(fn($s) => [
                'session_id'    => $s->id,
                'student_id'    => $s->student_id,
                'nim'           => $s->student?->nim,
                'name'          => $s->student?->name,
                'status'        => $s->status,
                'total_score'   => $s->total_score,
                'tab_switches'  => $s->tab_switches,
                'started_at'    => $s->started_at,
                'finished_at'   => $s->finished_at,
                'duration_sec'  => $s->started_at && $s->finished_at
                    ? $s->started_at->diffInSeconds($s->finished_at) : null,
            ]);

        $total = $sessions->count();
        $submitted = $sessions->where('status', 'SUBMITTED')->count();
        $avgScore = $submitted > 0 ? round($sessions->where('status', 'SUBMITTED')->avg('total_score'), 1) : 0;
        $maxScore = $exam->questions()->sum('score');

        return response()->json([
            'exam'       => $exam->only(['id', 'title', 'type', 'status', 'duration_minutes']),
            'max_score'  => $maxScore,
            'stats'      => ['total' => $total, 'submitted' => $submitted, 'avg_score' => $avgScore],
            'sessions'   => $sessions,
        ]);
    }

    /** Detail jawaban satu mahasiswa */
    public function studentResult(Exam $exam, Student $student): JsonResponse
    {
        $session = ExamSession::where('exam_id', $exam->id)
            ->where('student_id', $student->id)->first();

        $answers = ExamAnswer::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->with('question')
            ->get()
            ->map(fn($a) => [
                'question_id'    => $a->question_id,
                'question_text'  => $a->question?->question,
                'question_type'  => $a->question?->type,
                'correct_answer' => $a->question?->correct_answer,
                'options'        => $a->question?->options,
                'student_answer' => $a->answer,
                'is_correct'     => $a->is_correct,
                'score'          => $a->score,
                'max_score'      => $a->question?->score,
                'manual_score'   => $a->score,
            ]);

        return response()->json([
            'student' => $student->only(['id', 'nim', 'name']),
            'session' => $session,
            'answers' => $answers,
            'total_score' => $session?->total_score,
        ]);
    }

    /** Dosen koreksi manual soal esai */
    public function gradeAnswer(Request $request, Exam $exam, Student $student): JsonResponse
    {
        $request->validate([
            'question_id' => 'required|exists:exam_questions,id',
            'score'       => 'required|numeric|min:0',
            'feedback'    => 'nullable|string',
        ]);

        $answer = ExamAnswer::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->where('question_id', $request->question_id)
            ->first();

        if (!$answer) return response()->json(['message' => 'Jawaban tidak ditemukan.'], 404);

        $answer->update(['score' => $request->score, 'is_correct' => null]);

        // Recalculate total score
        $totalScore = ExamAnswer::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->sum('score');

        ExamSession::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->update(['total_score' => $totalScore]);

        return response()->json(['message' => 'Nilai berhasil disimpan.', 'total_score' => $totalScore]);
    }
}
