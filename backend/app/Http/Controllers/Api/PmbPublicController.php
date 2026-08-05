<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\PmbPeriod;
use App\Models\PmbPath;
use App\Models\PmbRegistrant;
use App\Models\StudyProgram;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PmbPublicController extends Controller
{
    /** Info periode PMB aktif (publik, tanpa auth) */
    public function activePeriod(): JsonResponse
    {
        $period = PmbPeriod::where('is_active', true)->with('academicYear')->first();
        return response()->json($period);
    }

    /** Daftar jalur seleksi (publik) */
    public function paths(): JsonResponse
    {
        return response()->json(PmbPath::where('is_active', true)->get());
    }

    /** Daftar prodi untuk pilihan (publik) */
    public function programs(): JsonResponse
    {
        return response()->json(
            StudyProgram::where('status', true)->select('id', 'code', 'name', 'degree', 'level')->get()
        );
    }

    /** Registrasi akun calon mahasiswa (publik) */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone'    => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'is_active' => true,
        ]);
        $user->assignRole('MAHASISWA');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Registrasi berhasil.',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ], 201);
    }

    // ============ ENDPOINTS YANG MEMERLUKAN AUTH (calon mahasiswa) ============

    /** Ambil data pendaftaran saya */
    public function myRegistration(): JsonResponse
    {
        $registrant = PmbRegistrant::where('user_id', auth()->id())
            ->with(['period', 'path', 'studyProgramChoice1', 'studyProgramChoice2', 'studyProgramChoice3', 'selectionResult', 'reRegistration'])
            ->latest()
            ->first();

        return response()->json($registrant);
    }

    /** Buat / update formulir pendaftaran */
    public function saveForm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pmb_period_id' => 'required|exists:pmb_periods,id',
            'pmb_path_id'   => 'nullable|exists:pmb_paths,id',
            // Data pribadi — semua nullable saat save draft
            'full_name'     => 'nullable|string|max:255',
            'gender'        => 'nullable|in:L,P',
            'birth_place'   => 'nullable|string|max:100',
            'birth_date'    => 'nullable|date',
            'religion'      => 'nullable|string|max:50',
            'nik'           => 'nullable|string|max:20',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email',
            'address'       => 'nullable|string',
            'province'      => 'nullable|string|max:100',
            'city'          => 'nullable|string|max:100',
            'district'      => 'nullable|string|max:100',
            'village'       => 'nullable|string|max:100',
            'postal_code'   => 'nullable|string|max:10',
            // Orang tua
            'father_name'       => 'nullable|string|max:255',
            'father_occupation' => 'nullable|string|max:100',
            'father_phone'      => 'nullable|string|max:20',
            'mother_name'       => 'nullable|string|max:255',
            'mother_occupation' => 'nullable|string|max:100',
            'mother_phone'      => 'nullable|string|max:20',
            'guardian_name'     => 'nullable|string|max:255',
            'guardian_occupation' => 'nullable|string|max:100',
            'guardian_phone'    => 'nullable|string|max:20',
            // Pendidikan
            'school_name'    => 'nullable|string|max:255',
            'school_address' => 'nullable|string',
            'graduation_year'=> 'nullable|integer|digits:4',
            'diploma_number' => 'nullable|string|max:100',
            // Pilihan prodi
            'choice_1'       => 'nullable|exists:study_programs,id',
            'choice_2'       => 'nullable|exists:study_programs,id',
            'choice_3'       => 'nullable|exists:study_programs,id',
            // Prestasi
            'achievement_description' => 'nullable|string',
            // Dokumen (link Google Drive)
            'diploma_link'     => 'nullable|string|max:500',
            'family_card_link' => 'nullable|string|max:500',
            'identity_link'    => 'nullable|string|max:500',
        ]);

        $registrant = PmbRegistrant::updateOrCreate(
            ['user_id' => auth()->id(), 'pmb_period_id' => $validated['pmb_period_id']],
            array_merge($validated, [
                'registration_number' => PmbRegistrant::where('user_id', auth()->id())
                    ->where('pmb_period_id', $validated['pmb_period_id'])
                    ->value('registration_number')
                    ?? PmbRegistrant::generateRegistrationNumber($validated['pmb_period_id']),
                'full_name' => $validated['full_name'] ?? auth()->user()->name,
                'gender'    => $validated['gender'] ?? 'L',
                'email'     => $validated['email'] ?? auth()->user()->email,
            ])
        );

        return response()->json(['message' => 'Formulir berhasil disimpan.', 'data' => $registrant]);
    }

    /** Upload pas foto */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => 'required|image|mimes:jpeg,png|max:2048']);

        $registrant = PmbRegistrant::where('user_id', auth()->id())->latest()->firstOrFail();

        if ($registrant->photo_path) {
            Storage::disk('public')->delete($registrant->photo_path);
        }

        $path = $request->file('photo')->store('pmb/photos', 'public');
        $registrant->update(['photo_path' => $path]);

        return response()->json([
            'message'    => 'Foto berhasil diupload.',
            'photo_url'  => Storage::disk('public')->url($path),
        ]);
    }

    /** Submit formulir (DRAFT → SUBMITTED) */
    public function submit(): JsonResponse
    {
        $registrant = PmbRegistrant::where('user_id', auth()->id())->latest()->firstOrFail();

        if ($registrant->status !== 'DRAFT') {
            return response()->json(['message' => 'Formulir sudah disubmit sebelumnya.'], 422);
        }

        // Validasi kelengkapan minimal
        $required = ['full_name', 'gender', 'birth_place', 'birth_date', 'choice_1', 'photo_path'];
        foreach ($required as $field) {
            if (empty($registrant->$field)) {
                return response()->json(['message' => "Field {$field} belum diisi. Lengkapi formulir terlebih dahulu."], 422);
            }
        }

        $registrant->update(['status' => 'SUBMITTED']);

        return response()->json(['message' => 'Formulir berhasil disubmit.', 'data' => $registrant->fresh()]);
    }

    /** Konfirmasi pembayaran */
    public function confirmPayment(Request $request): JsonResponse
    {
        $request->validate(['payment_proof' => 'nullable|string|max:500']);

        $registrant = PmbRegistrant::where('user_id', auth()->id())->latest()->firstOrFail();
        $registrant->update([
            'is_paid'       => true,
            'payment_proof' => $request->payment_proof,
            'paid_at'       => now(),
            'status'        => 'MENUNGGU_VERIFIKASI',
        ]);

        return response()->json(['message' => 'Pembayaran berhasil dikonfirmasi.', 'data' => $registrant->fresh()]);
    }

    /** Lihat hasil seleksi */
    public function result(): JsonResponse
    {
        $registrant = PmbRegistrant::where('user_id', auth()->id())
            ->with(['selectionResult', 'acceptedProgram'])
            ->latest()
            ->firstOrFail();

        return response()->json([
            'status'           => $registrant->status,
            'accepted_program' => $registrant->acceptedProgram,
            'selection_result' => $registrant->selectionResult,
        ]);
    }

    /** Download kartu peserta PDF */
    public function downloadCard()
    {
        $registrant = PmbRegistrant::where('user_id', auth()->id())
            ->with(['period', 'path', 'studyProgramChoice1', 'studyProgramChoice2', 'studyProgramChoice3'])
            ->latest()
            ->firstOrFail();

        // Hanya bisa cetak jika sudah terverifikasi
        $allowedStatuses = ['TERVERIFIKASI', 'MENGIKUTI_SELEKSI', 'LULUS', 'TIDAK_LULUS', 'DAFTAR_ULANG', 'MAHASISWA_BARU'];
        if (!in_array($registrant->status, $allowedStatuses)) {
            return response()->json(['message' => 'Kartu peserta belum tersedia. Status pendaftaran belum terverifikasi.'], 422);
        }

        $institution = Institution::first();

        $pdf = Pdf::loadView('pdf.kartu-peserta', [
            'registrant'  => $registrant,
            'institution' => $institution,
        ])->setPaper('a5', 'landscape')
          ->setOption('dpi', 96)
          ->setOption('defaultFont', 'DejaVu Sans');

        $filename = "kartu-peserta-{$registrant->registration_number}.pdf";

        return $pdf->download($filename);
    }
}
