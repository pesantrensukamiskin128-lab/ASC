<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = AuditLog::with('user:id,name,email')
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->action, fn($q) => $q->where('action', $request->action))
            ->when($request->model_type, fn($q) => $q->where('model_type', 'like', "%{$request->model_type}%"))
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('action', 'like', "%{$request->search}%")
                   ->orWhere('model_type', 'like', "%{$request->search}%")
                   ->orWhereHas('user', fn($q3) => $q3->where('name', 'like', "%{$request->search}%"));
            }))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($data);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        return response()->json($auditLog->load('user:id,name,email'));
    }

    /** Daftar action unik untuk filter */
    public function actions(): JsonResponse
    {
        $actions = AuditLog::distinct()->pluck('action')->sort()->values();
        return response()->json($actions);
    }

    /** Daftar model_type unik untuk filter */
    public function modelTypes(): JsonResponse
    {
        $types = AuditLog::distinct()
            ->whereNotNull('model_type')
            ->pluck('model_type')
            ->map(fn($t) => class_basename($t))
            ->unique()->sort()->values();
        return response()->json($types);
    }
}
