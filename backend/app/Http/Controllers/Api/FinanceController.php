<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Scholarship;
use App\Models\Student;
use App\Models\StudentScholarship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    // =============================================
    // FEE TYPES
    // =============================================

    public function feeTypes(): JsonResponse
    {
        return response()->json(FeeType::orderBy('name')->get());
    }

    public function storeFeeType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'         => 'required|string|max:30|unique:fee_types',
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'is_active'    => 'boolean',
            'is_mandatory' => 'boolean',
            'is_recurring' => 'boolean',
        ]);

        $feeType = FeeType::create($validated);
        return response()->json(['message' => 'Jenis tagihan berhasil ditambahkan.', 'data' => $feeType], 201);
    }

    public function updateFeeType(Request $request, FeeType $feeType): JsonResponse
    {
        $validated = $request->validate([
            'code'         => "sometimes|string|max:30|unique:fee_types,code,{$feeType->id}",
            'name'         => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'is_active'    => 'boolean',
            'is_mandatory' => 'boolean',
            'is_recurring' => 'boolean',
        ]);

        $feeType->update($validated);
        return response()->json(['message' => 'Jenis tagihan berhasil diupdate.', 'data' => $feeType]);
    }

    public function destroyFeeType(FeeType $feeType): JsonResponse
    {
        if ($feeType->invoiceItems()->count() > 0) {
            return response()->json(['message' => 'Tidak dapat menghapus jenis tagihan yang sudah digunakan.'], 422);
        }
        $feeType->structures()->delete();
        $feeType->delete();
        return response()->json(['message' => 'Jenis tagihan berhasil dihapus.']);
    }

    // =============================================
    // FEE STRUCTURES
    // =============================================

    public function feeStructures(Request $request): JsonResponse
    {
        $data = FeeStructure::with(['feeType', 'studyProgram', 'academicYear'])
            ->when($request->fee_type_id, fn($q) => $q->where('fee_type_id', $request->fee_type_id))
            ->when($request->study_program_id, fn($q) => $q->where('study_program_id', $request->study_program_id))
            ->when($request->academic_year_id, fn($q) => $q->where('academic_year_id', $request->academic_year_id))
            ->paginate($request->per_page ?? 20);

        return response()->json($data);
    }

    public function storeFeeStructure(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fee_type_id'      => 'required|exists:fee_types,id',
            'study_program_id' => 'nullable|exists:study_programs,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'academic_level'   => 'nullable|integer|min:1|max:8',
            'amount'           => 'required|numeric|min:0',
            'note'             => 'nullable|string',
        ]);

        $structure = FeeStructure::create($validated);
        return response()->json(['message' => 'Struktur biaya berhasil ditambahkan.', 'data' => $structure->load(['feeType', 'studyProgram', 'academicYear'])], 201);
    }

    public function updateFeeStructure(Request $request, FeeStructure $feeStructure): JsonResponse
    {
        $validated = $request->validate([
            'fee_type_id'      => 'sometimes|exists:fee_types,id',
            'study_program_id' => 'nullable|exists:study_programs,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'academic_level'   => 'nullable|integer|min:1|max:8',
            'amount'           => 'sometimes|numeric|min:0',
            'note'             => 'nullable|string',
        ]);

        $feeStructure->update($validated);
        return response()->json(['message' => 'Struktur biaya berhasil diupdate.', 'data' => $feeStructure->fresh(['feeType', 'studyProgram', 'academicYear'])]);
    }

    public function destroyFeeStructure(FeeStructure $feeStructure): JsonResponse
    {
        $feeStructure->delete();
        return response()->json(['message' => 'Struktur biaya berhasil dihapus.']);
    }

    // =============================================
    // INVOICES (TAGIHAN)
    // =============================================

    public function invoices(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Invoice::with(['student.studyProgram', 'semester.academicYear', 'items.feeType'])
            ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
            ->when($request->semester_id, fn($q) => $q->where('semester_id', $request->semester_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->whereHas('student', fn($q2) =>
                $q2->where('name', 'like', "%{$request->search}%")
                   ->orWhere('nim', 'like', "%{$request->search}%")));

        // Mahasiswa hanya lihat tagihan sendiri
        if ($user->hasRole('MAHASISWA') && $user->student) {
            $query->where('student_id', $user->student->id);
        }

        return response()->json($query->orderByDesc('invoice_date')->paginate($request->per_page ?? 20));
    }

    public function showInvoice(Invoice $invoice): JsonResponse
    {
        return response()->json($invoice->load([
            'student.studyProgram', 'semester.academicYear',
            'items.feeType', 'payments.verifiedBy', 'createdBy',
        ]));
    }

    public function storeInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'  => 'required|exists:students,id',
            'semester_id' => 'nullable|exists:semesters,id',
            'due_date'    => 'required|date',
            'note'        => 'nullable|string',
            'discount_amount'     => 'nullable|numeric|min:0',
            'scholarship_amount'  => 'nullable|numeric|min:0',
            'items'               => 'required|array|min:1',
            'items.*.fee_type_id' => 'required|exists:fee_types,id',
            'items.*.description' => 'nullable|string',
            'items.*.amount'      => 'required|numeric|min:0',
        ]);

        $invoice = DB::transaction(function () use ($validated) {
            $totalAmount = collect($validated['items'])->sum('amount');

            $invoice = Invoice::create([
                'invoice_number'     => Invoice::generateInvoiceNumber($validated['semester_id'] ?? 0),
                'student_id'         => $validated['student_id'],
                'semester_id'        => $validated['semester_id'] ?? null,
                'invoice_date'       => now()->toDateString(),
                'due_date'           => $validated['due_date'],
                'total_amount'       => $totalAmount,
                'discount_amount'    => $validated['discount_amount'] ?? 0,
                'scholarship_amount' => $validated['scholarship_amount'] ?? 0,
                'status'             => 'UNPAID',
                'note'               => $validated['note'] ?? null,
                'created_by'         => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'fee_type_id' => $item['fee_type_id'],
                    'description' => $item['description'] ?? null,
                    'amount'      => $item['amount'],
                ]);
            }

            return $invoice;
        });

        // Kirim notifikasi ke mahasiswa
        $this->notifyStudentInvoice($invoice);

        return response()->json([
            'message' => 'Tagihan berhasil dibuat.',
            'data'    => $invoice->load(['student', 'items.feeType']),
        ], 201);
    }

    /** Kirim notifikasi tagihan ke mahasiswa */
    private function notifyStudentInvoice(Invoice $invoice): void
    {
        $student = $invoice->student;
        if (!$student?->user_id) return;

        $amount = number_format($invoice->total_amount, 0, ',', '.');
        \App\Models\AppNotification::send(
            $student->user_id,
            'Tagihan Baru',
            "Anda memiliki tagihan baru sebesar Rp {$amount}. Jatuh tempo: " . ($invoice->due_date?->format('d/m/Y') ?? '-'),
            'warning',
            '/keuangan/saya'
        );
    }

    /** Generate tagihan otomatis untuk semua mahasiswa aktif per semester */
    public function generateBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id'      => 'required|exists:semesters,id',
            'study_program_id' => 'nullable|exists:study_programs,id',
            'due_date'         => 'required|date',
        ]);

        $students = Student::where('status', 'Aktif')
            ->when($validated['study_program_id'] ?? null, fn($q, $v) => $q->where('study_program_id', $v))
            ->get();

        $count = 0;

        DB::transaction(function () use ($students, $validated, &$count) {
            foreach ($students as $student) {
                // Cek sudah ada tagihan untuk semester ini
                if (Invoice::where('student_id', $student->id)->where('semester_id', $validated['semester_id'])->exists()) {
                    continue;
                }

                // Ambil fee structures sesuai prodi dan level
                $structures = FeeStructure::where('study_program_id', $student->study_program_id)
                    ->whereHas('feeType', fn($q) => $q->where('is_recurring', true)->where('is_active', true))
                    ->get();

                if ($structures->isEmpty()) {
                    // Fallback: ambil struktur umum (tanpa prodi)
                    $structures = FeeStructure::whereNull('study_program_id')
                        ->whereHas('feeType', fn($q) => $q->where('is_recurring', true)->where('is_active', true))
                        ->get();
                }

                if ($structures->isEmpty()) continue;

                $totalAmount = $structures->sum('amount');

                $invoice = Invoice::create([
                    'invoice_number'  => Invoice::generateInvoiceNumber($validated['semester_id']),
                    'student_id'      => $student->id,
                    'semester_id'     => $validated['semester_id'],
                    'invoice_date'    => now()->toDateString(),
                    'due_date'        => $validated['due_date'],
                    'total_amount'    => $totalAmount,
                    'status'          => 'UNPAID',
                    'created_by'      => auth()->id(),
                ]);

                foreach ($structures as $struct) {
                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'fee_type_id' => $struct->fee_type_id,
                        'description' => $struct->feeType->name,
                        'amount'      => $struct->amount,
                    ]);
                }

                $count++;
            }
        });

        // Kirim notifikasi ke semua mahasiswa yang baru dapat tagihan
        $newInvoices = Invoice::where('semester_id', $validated['semester_id'])
            ->where('created_by', auth()->id())
            ->where('created_at', '>=', now()->subMinutes(2))
            ->with('student')
            ->get();
        foreach ($newInvoices as $inv) {
            $this->notifyStudentInvoice($inv);
        }

        return response()->json(['message' => "Berhasil generate {$count} tagihan.", 'count' => $count]);
    }

    public function cancelInvoice(Invoice $invoice): JsonResponse
    {
        if ($invoice->paid_amount > 0) {
            return response()->json(['message' => 'Tidak dapat membatalkan tagihan yang sudah ada pembayaran.'], 422);
        }
        $invoice->update(['status' => 'CANCELLED']);
        return response()->json(['message' => 'Tagihan berhasil dibatalkan.']);
    }

    public function destroyInvoice(Invoice $invoice): JsonResponse
    {
        if ($invoice->paid_amount > 0 || $invoice->payments()->where('status', 'VERIFIED')->exists()) {
            return response()->json(['message' => 'Tidak dapat menghapus tagihan yang sudah ada pembayaran terverifikasi.'], 422);
        }
        $invoice->payments()->delete();
        $invoice->items()->delete();
        $invoice->delete();
        return response()->json(['message' => 'Tagihan berhasil dihapus.']);
    }

    public function waiveInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate(['note' => 'required|string']);
        $invoice->update(['status' => 'WAIVED', 'note' => $request->note]);
        return response()->json(['message' => 'Tagihan berhasil dibebaskan.']);
    }

    // =============================================
    // PAYMENTS (PEMBAYARAN)
    // =============================================

    public function payments(Request $request): JsonResponse
    {
        $query = Payment::with(['invoice', 'student.studyProgram', 'verifiedBy'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
            ->when($request->search, fn($q) => $q->whereHas('student', fn($q2) =>
                $q2->where('name', 'like', "%{$request->search}%")
                   ->orWhere('nim', 'like', "%{$request->search}%")));

        $user = auth()->user();
        if ($user->hasRole('MAHASISWA') && $user->student) {
            $query->where('student_id', $user->student->id);
        }

        return response()->json($query->orderByDesc('payment_date')->paginate($request->per_page ?? 20));
    }

    public function storePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id'       => 'required|exists:invoices,id',
            'amount'           => 'required|numeric|min:1',
            'payment_method'   => 'required|string|max:50',
            'payment_date'     => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'bank_name'        => 'nullable|string|max:100',
            'account_number'   => 'nullable|string|max:50',
            'account_name'     => 'nullable|string|max:100',
            'note'             => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        if (in_array($invoice->status, ['PAID', 'CANCELLED', 'WAIVED'])) {
            return response()->json(['message' => 'Tagihan sudah lunas/dibatalkan.'], 422);
        }

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'student_id'       => $invoice->student_id,
            'payment_number'   => Payment::generatePaymentNumber(),
            'amount'           => $validated['amount'],
            'payment_method'   => $validated['payment_method'],
            'payment_date'     => $validated['payment_date'],
            'reference_number' => $validated['reference_number'] ?? null,
            'bank_name'        => $validated['bank_name'] ?? null,
            'account_number'   => $validated['account_number'] ?? null,
            'account_name'     => $validated['account_name'] ?? null,
            'note'             => $validated['note'] ?? null,
            'status'           => 'PENDING',
        ]);

        return response()->json(['message' => 'Pembayaran berhasil dicatat, menunggu verifikasi.', 'data' => $payment], 201);
    }

    public function verifyPayment(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status !== 'PENDING') {
            return response()->json(['message' => 'Pembayaran sudah diproses.'], 422);
        }

        $request->validate(['action' => 'required|in:verify,reject', 'reason' => 'nullable|string']);

        if ($request->action === 'verify') {
            $payment->update([
                'status'      => 'VERIFIED',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);
            // Update invoice
            $payment->invoice->recalculateStatus();
            return response()->json(['message' => 'Pembayaran berhasil diverifikasi.']);
        } else {
            $payment->update([
                'status'           => 'REJECTED',
                'rejection_reason' => $request->reason ?? 'Ditolak oleh admin.',
                'verified_by'      => auth()->id(),
                'verified_at'      => now(),
            ]);
            return response()->json(['message' => 'Pembayaran ditolak.']);
        }
    }

    // =============================================
    // SCHOLARSHIPS (BEASISWA)
    // =============================================

    public function scholarships(): JsonResponse
    {
        return response()->json(Scholarship::withCount('students')->orderBy('name')->get());
    }

    public function storeScholarship(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'         => 'required|string|max:30|unique:scholarships',
            'name'         => 'required|string|max:255',
            'provider'     => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'requirements' => 'nullable|string',
            'amount'       => 'nullable|numeric|min:0',
            'type'         => 'required|in:FULL,PARTIAL,TUITION_ONLY,LIVING_COST',
            'is_active'    => 'boolean',
        ]);

        $scholarship = Scholarship::create($validated);
        return response()->json(['message' => 'Beasiswa berhasil ditambahkan.', 'data' => $scholarship], 201);
    }

    public function updateScholarship(Request $request, Scholarship $scholarship): JsonResponse
    {
        $validated = $request->validate([
            'code'         => "sometimes|string|max:30|unique:scholarships,code,{$scholarship->id}",
            'name'         => 'sometimes|string|max:255',
            'provider'     => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'requirements' => 'nullable|string',
            'amount'       => 'nullable|numeric|min:0',
            'type'         => 'sometimes|in:FULL,PARTIAL,TUITION_ONLY,LIVING_COST',
            'is_active'    => 'boolean',
        ]);

        $scholarship->update($validated);
        return response()->json(['message' => 'Beasiswa berhasil diupdate.', 'data' => $scholarship]);
    }

    public function destroyScholarship(Scholarship $scholarship): JsonResponse
    {
        if ($scholarship->students()->count() > 0) {
            return response()->json(['message' => 'Tidak dapat menghapus beasiswa yang sudah memiliki penerima.'], 422);
        }
        $scholarship->delete();
        return response()->json(['message' => 'Beasiswa berhasil dihapus.']);
    }

    public function studentScholarships(Request $request): JsonResponse
    {
        $data = StudentScholarship::with(['student.studyProgram', 'scholarship', 'academicYear', 'semester'])
            ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
            ->when($request->scholarship_id, fn($q) => $q->where('scholarship_id', $request->scholarship_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->paginate($request->per_page ?? 20);

        return response()->json($data);
    }

    public function assignScholarship(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'      => 'required|exists:students,id',
            'scholarship_id'  => 'required|exists:scholarships,id',
            'academic_year_id'=> 'nullable|exists:academic_years,id',
            'semester_id'     => 'nullable|exists:semesters,id',
            'amount'          => 'required|numeric|min:0',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date',
            'note'            => 'nullable|string',
        ]);

        $studentScholarship = StudentScholarship::create(array_merge($validated, ['status' => 'AKTIF']));
        return response()->json(['message' => 'Beasiswa berhasil diberikan.', 'data' => $studentScholarship->load(['student', 'scholarship'])], 201);
    }

    public function revokeScholarship(StudentScholarship $studentScholarship): JsonResponse
    {
        $studentScholarship->update(['status' => 'DICABUT']);
        return response()->json(['message' => 'Beasiswa berhasil dicabut.']);
    }

    // =============================================
    // SUMMARY & REPORTS
    // =============================================

    /** Mahasiswa submit pembayaran + upload bukti */
    public function studentPayment(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id'       => 'required|exists:invoices,id',
            'amount'           => 'required|numeric|min:1',
            'payment_method'   => 'required|string|max:50',
            'payment_date'     => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'bank_name'        => 'nullable|string|max:100',
            'account_name'     => 'nullable|string|max:100',
            'note'             => 'nullable|string',
            'receipt'          => 'nullable|file|mimes:jpeg,png,pdf,webp|max:5120',
        ]);

        $student = auth()->user()->student;
        if (!$student) {
            return response()->json(['message' => 'Data mahasiswa tidak ditemukan.'], 404);
        }

        $invoice = Invoice::where('id', $request->invoice_id)
            ->where('student_id', $student->id)
            ->first();

        if (!$invoice) {
            return response()->json(['message' => 'Tagihan tidak ditemukan.'], 404);
        }

        if (in_array($invoice->status, ['PAID', 'CANCELLED', 'WAIVED'])) {
            return response()->json(['message' => 'Tagihan sudah lunas/dibatalkan.'], 422);
        }

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('payment-receipts', 'public');
        }

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'student_id'       => $student->id,
            'payment_number'   => Payment::generatePaymentNumber(),
            'amount'           => $request->amount,
            'payment_method'   => $request->payment_method,
            'payment_date'     => $request->payment_date,
            'reference_number' => $request->reference_number,
            'bank_name'        => $request->bank_name,
            'account_name'     => $request->account_name,
            'receipt_path'     => $receiptPath,
            'note'             => $request->note,
            'status'           => 'PENDING',
        ]);

        return response()->json(['message' => 'Pembayaran berhasil dikirim, menunggu verifikasi admin.', 'data' => $payment], 201);
    }

    /** Cek status keuangan mahasiswa (untuk integrasi KRS) */
    public function checkPaymentStatus(Request $request): JsonResponse
    {
        $request->validate(['student_id' => 'required|exists:students,id', 'semester_id' => 'required|exists:semesters,id']);

        $unpaid = Invoice::where('student_id', $request->student_id)
            ->where('semester_id', $request->semester_id)
            ->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
            ->exists();

        return response()->json([
            'is_clear' => !$unpaid,
            'message'  => $unpaid ? 'Masih ada tagihan semester ini yang belum lunas.' : 'Keuangan clear.',
        ]);
    }

    /** Rekap keuangan mahasiswa */
    public function studentSummary(Request $request): JsonResponse
    {
        $studentId = $request->student_id ?? auth()->user()->student?->id;
        if (!$studentId) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        $totalInvoiced = Invoice::where('student_id', $studentId)->whereNotIn('status', ['CANCELLED', 'WAIVED'])->sum('total_amount');
        $totalDiscount = Invoice::where('student_id', $studentId)->whereNotIn('status', ['CANCELLED', 'WAIVED'])->sum('discount_amount');
        $totalScholarship = Invoice::where('student_id', $studentId)->whereNotIn('status', ['CANCELLED', 'WAIVED'])->sum('scholarship_amount');
        $totalPaid = Payment::where('student_id', $studentId)->where('status', 'VERIFIED')->sum('amount');
        $outstanding = $totalInvoiced - $totalDiscount - $totalScholarship - $totalPaid;

        return response()->json([
            'total_invoiced'    => $totalInvoiced,
            'total_discount'    => $totalDiscount,
            'total_scholarship' => $totalScholarship,
            'total_paid'        => $totalPaid,
            'outstanding'       => max(0, $outstanding),
        ]);
    }

    /** Dashboard statistik keuangan */
    public function dashboard(Request $request): JsonResponse
    {
        $semesterId = $request->semester_id;

        $query = Invoice::when($semesterId, fn($q) => $q->where('semester_id', $semesterId));

        $totalInvoiced   = (clone $query)->whereNotIn('status', ['CANCELLED', 'WAIVED'])->sum('total_amount');
        $totalPaid       = Payment::when($semesterId, fn($q) => $q->whereHas('invoice', fn($q2) => $q2->where('semester_id', $semesterId)))->where('status', 'VERIFIED')->sum('amount');
        $pendingPayments = Payment::when($semesterId, fn($q) => $q->whereHas('invoice', fn($q2) => $q2->where('semester_id', $semesterId)))->where('status', 'PENDING')->count();
        $unpaidInvoices  = (clone $query)->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])->count();
        $overdueInvoices = (clone $query)->where('status', 'OVERDUE')->count();

        return response()->json([
            'total_invoiced'   => $totalInvoiced,
            'total_paid'       => $totalPaid,
            'total_outstanding'=> max(0, $totalInvoiced - $totalPaid),
            'pending_payments' => $pendingPayments,
            'unpaid_invoices'  => $unpaidInvoices,
            'overdue_invoices' => $overdueInvoices,
        ]);
    }
}
