<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Room::with('building')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('code', 'like', "%{$request->search}%"))
            ->when($request->building_id, fn($q) => $q->where('building_id', $request->building_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'code'        => 'required|string|max:20|unique:rooms',
            'name'        => 'required|string|max:255',
            'floor'       => 'nullable|integer|min:1',
            'capacity'    => 'nullable|integer|min:1',
            'type'        => 'required|in:Kelas,Lab,Aula,Seminar,Kantor,Lainnya',
            'facilities'  => 'nullable|array',
            'status'      => 'boolean',
        ]);

        $room = Room::create($validated);

        return response()->json(['message' => 'Ruangan berhasil ditambahkan.', 'data' => $room->load('building')], 201);
    }

    public function show(Room $room): JsonResponse
    {
        return response()->json($room->load('building'));
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'building_id' => 'sometimes|exists:buildings,id',
            'code'        => "sometimes|string|max:20|unique:rooms,code,{$room->id}",
            'name'        => 'sometimes|string|max:255',
            'floor'       => 'nullable|integer|min:1',
            'capacity'    => 'nullable|integer|min:1',
            'type'        => 'sometimes|in:Kelas,Lab,Aula,Seminar,Kantor,Lainnya',
            'facilities'  => 'nullable|array',
            'status'      => 'boolean',
        ]);

        $room->update($validated);

        return response()->json(['message' => 'Ruangan berhasil diupdate.', 'data' => $room->fresh('building')]);
    }

    public function destroy(Room $room): JsonResponse
    {
        $room->delete();
        return response()->json(['message' => 'Ruangan berhasil dihapus.']);
    }

    public function all(Request $request): JsonResponse
    {
        return response()->json(
            Room::where('status', true)
                ->when($request->building_id, fn($q) => $q->where('building_id', $request->building_id))
                ->when($request->type, fn($q) => $q->where('type', $request->type))
                ->with('building:id,name,code')
                ->select('id', 'code', 'name', 'capacity', 'type', 'building_id')
                ->get()
        );
    }
}
