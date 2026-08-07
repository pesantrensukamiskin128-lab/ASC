<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LetterTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LetterTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = LetterTemplate::with(['creator', 'letterType'])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderByDesc('updated_at')
            ->paginate($request->per_page ?? 20);
        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'letter_type_id'  => 'nullable|exists:letter_types,id',
            'subject'         => 'nullable|string|max:500',
            'recipient'       => 'nullable|string',
            'attachment_note' => 'nullable|string|max:255',
            'city'            => 'nullable|string|max:100',
            'body'            => 'required|string',
            'appendix_body'   => 'nullable|string',
            'is_shared'       => 'boolean',
        ]);
        $validated['created_by'] = auth()->id();
        $template = LetterTemplate::create($validated);
        return response()->json(['message' => 'Template berhasil disimpan.', 'data' => $template], 201);
    }

    public function show(LetterTemplate $letterTemplate): JsonResponse
    {
        return response()->json($letterTemplate->load(['creator', 'letterType']));
    }

    public function update(Request $request, LetterTemplate $letterTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'description'     => 'nullable|string',
            'letter_type_id'  => 'nullable|exists:letter_types,id',
            'subject'         => 'nullable|string|max:500',
            'recipient'       => 'nullable|string',
            'attachment_note' => 'nullable|string|max:255',
            'city'            => 'nullable|string|max:100',
            'body'            => 'sometimes|string',
            'appendix_body'   => 'nullable|string',
            'is_shared'       => 'boolean',
        ]);
        $letterTemplate->update($validated);
        return response()->json(['message' => 'Template berhasil diupdate.', 'data' => $letterTemplate->fresh()]);
    }

    public function destroy(LetterTemplate $letterTemplate): JsonResponse
    {
        $letterTemplate->delete();
        return response()->json(['message' => 'Template berhasil dihapus.']);
    }
}
