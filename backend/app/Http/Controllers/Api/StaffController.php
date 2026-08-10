<?php

namespace App\Http\Controllers\Api;

use App\Exports\StaffExport;
use App\Http\Controllers\Controller;
use App\Imports\StaffImport;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Staff::with('user')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('nip', 'like', "%{$request->search}%"))
            ->when($request->department, fn($q) => $q->where('department', $request->department))
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nip'               => 'nullable|string|max:30|unique:staff',
            'name'              => 'required|string|max:255',
            'gender'            => 'nullable|in:L,P',
            'birth_place'       => 'nullable|string|max:100',
            'birth_date'        => 'nullable|date',
            'email'             => 'nullable|email',
            'phone'             => 'nullable|string|max:20',
            'address'           => 'nullable|string',
            'position'          => 'nullable|string|max:100',
            'department'        => 'nullable|string|max:100',
            'employment_status' => 'nullable|string|max:50',
        ]);

        if (!empty($validated['nip'])) {
            $email = $validated['email'] ?? $validated['nip'] . '@staff.jawami.ac.id';
            $user  = User::create([
                'name'     => $validated['name'],
                'email'    => $email,
                'username' => $validated['nip'],
                'password' => Hash::make($validated['nip']),
            ]);
            $validated['user_id'] = $user->id;
        }

        $staff = Staff::create($validated);

        return response()->json(['message' => 'Data tenaga kependidikan berhasil ditambahkan.', 'data' => $staff], 201);
    }

    public function show(Staff $staff): JsonResponse
    {
        return response()->json($staff->load('user'));
    }

    public function update(Request $request, Staff $staff): JsonResponse
    {
        $validated = $request->validate([
            'nip'               => "nullable|string|max:30|unique:staff,nip,{$staff->id}",
            'name'              => 'sometimes|string|max:255',
            'gender'            => 'nullable|in:L,P',
            'birth_place'       => 'nullable|string|max:100',
            'birth_date'        => 'nullable|date',
            'email'             => 'nullable|email',
            'phone'             => 'nullable|string|max:20',
            'address'           => 'nullable|string',
            'position'          => 'nullable|string|max:100',
            'department'        => 'nullable|string|max:100',
            'employment_status' => 'nullable|string|max:50',
            'status'            => 'boolean',
        ]);

        $staff->update($validated);

        return response()->json(['message' => 'Data berhasil diupdate.', 'data' => $staff->fresh()]);
    }

    public function destroy(Staff $staff): JsonResponse
    {
        $staff->delete();
        return response()->json(['message' => 'Data tenaga kependidikan berhasil dihapus.']);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'exists:staff,id']);
        $count = Staff::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => "{$count} data tenaga kependidikan berhasil dihapus."]);
    }

    public function export(Request $request)
    {
        $filename = 'tenaga-kependidikan-' . now()->format('Ymd-His') . '.xlsx';
        return Excel::download(
            new StaffExport($request->department),
            $filename
        );
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        $import = new StaffImport();
        Excel::import($import, $request->file('file'));

        $errors = collect($import->errors())->map(fn($e) => $e->getMessage())->values();

        return response()->json([
            'message' => 'Import selesai.' . ($errors->count() ? ' Beberapa baris dilewati.' : ''),
            'errors'  => $errors,
        ]);
    }
}
