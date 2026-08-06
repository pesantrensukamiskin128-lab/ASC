<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%"))
            ->when($request->role, fn($q) => $q->role($request->role))
            ->paginate($request->per_page ?? 15);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'username' => 'nullable|string|unique:users',
            'password' => 'required|string|min:8',
            'role'     => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'username' => $validated['username'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'user'    => $user->load('roles'),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('roles', 'permissions'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'email'     => "sometimes|email|unique:users,email,{$user->id}",
            'username'  => "sometimes|string|unique:users,username,{$user->id}",
            'password'  => 'sometimes|string|min:8',
            'is_active' => 'sometimes|boolean',
            'role'      => 'sometimes|string|exists:roles,name',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update(collect($validated)->except('role')->toArray());

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return response()->json([
            'message' => 'User berhasil diupdate.',
            'user'    => $user->fresh('roles'),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        return response()->json(['message' => 'User berhasil dihapus.']);
    }

    public function roles(): JsonResponse
    {
        // Hanya tampilkan role yang aktif dipakai (role lama sudah dihapus oleh migration)
        $validRoles = ['SUPER_ADMIN', 'ADMIN_AKADEMIK', 'ADMIN_PMB', 'ADMIN_KEUANGAN', 'ADMIN_UMUM', 'KEPALA_TU', 'DOSEN', 'MAHASISWA', 'ALUMNI'];

        return response()->json(
            Role::whereIn('name', $validRoles)->get(['id', 'name'])
        );
    }

    /** Daftar user ringkas — untuk pilih penerima surat, disposisi, peserta agenda */
    public function list(Request $request): JsonResponse
    {
        $users = User::where('is_active', true)
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return response()->json($users);
    }
}
