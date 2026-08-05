<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            LoginAttempt::record($request->email, false, 'Invalid credentials');
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            LoginAttempt::record($request->email, false, 'Account inactive');
            return response()->json(['message' => 'Akun Anda tidak aktif.'], 403);
        }

        // Cek apakah 2FA aktif
        if ($user->two_factor_enabled) {
            // Jangan buat token permanen dulu, minta verifikasi 2FA
            $tempToken = $user->createToken('2fa_pending', ['2fa:verify'], now()->addMinutes(5))->plainTextToken;

            LoginAttempt::record($request->email, true, '2FA pending');

            return response()->json([
                'requires_2fa' => true,
                'temp_token' => $tempToken,
                'message' => 'Masukkan kode 2FA untuk melanjutkan.',
            ]);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        LoginAttempt::record($request->email, true);
        AuditLog::record('LOGIN', 'User', $user->id);

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'username' => $user->username,
                'roles'    => $user->getRoleNames(),
                'permissions' => $user->getAllEffectivePermissions(),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $response = [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'username'       => $user->username,
            'is_active'      => $user->is_active,
            'last_login_at'  => $user->last_login_at,
            'roles'          => $user->getRoleNames(),
            'permissions'    => $user->getAllEffectivePermissions(),
        ];

        // Sertakan data student/lecturer jika ada
        if ($user->student) {
            $response['student'] = [
                'id'  => $user->student->id,
                'nim' => $user->student->nim,
                'name' => $user->student->name,
                'study_program_id' => $user->student->study_program_id,
            ];
        }
        if ($user->lecturer) {
            $response['lecturer'] = [
                'id'   => $user->lecturer->id,
                'name' => $user->lecturer->name,
            ];
        }

        return response()->json($response);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name'                  => 'sometimes|required|string|max:255',
            'email'                 => "sometimes|required|email|unique:users,email,{$user->id}",
            'username'              => "sometimes|nullable|string|max:50|unique:users,username,{$user->id}",
            'current_password'      => 'required_with:new_password|string',
            'new_password'          => 'sometimes|nullable|string|min:8|confirmed',
            'new_password_confirmation' => 'sometimes|nullable|string',
        ]);

        // Verifikasi password lama sebelum ganti password baru
        if (isset($validated['new_password']) && $validated['new_password']) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'Password saat ini tidak sesuai.',
                    'errors'  => ['current_password' => ['Password saat ini tidak sesuai.']],
                ], 422);
            }
            $user->password = Hash::make($validated['new_password']);
        }

        $user->name     = $validated['name']     ?? $user->name;
        $user->email    = $validated['email']    ?? $user->email;
        $user->username = array_key_exists('username', $validated) ? $validated['username'] : $user->username;
        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user'    => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'username'      => $user->username,
                'is_active'     => $user->is_active,
                'last_login_at' => $user->last_login_at,
                'roles'         => $user->getRoleNames(),
                'permissions'   => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Semua sesi berhasil dihapus.']);
    }
}
