<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;

        $paginator = AuditLog::with('user:id,username')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $logsData = [];
        foreach ($paginator->items() as $log) {
            /** @var AuditLog $log */
            /** @var \App\Models\User|null $logUser */
            $logUser = $log->user;
            $logsData[] = [
                'id' => $log->id,
                'action' => $log->action,
                'admin' => $logUser !== null ? ['id' => $logUser->id, 'username' => $logUser->username] : null,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'data' => $log->data,
                'comment' => $log->comment,
                'via_api_key' => $log->via_api_key,
                'created_at' => $log->created_at->toISOString(),
            ];
        }

        return response()->json([
            'logs' => $logsData,
            'total' => $paginator->total(),
            'per_page' => $perPage,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function bulkComment(Request $request): JsonResponse
    {
        /** @var array{ids: int[], comment: string|null} $validated */
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $admin */
        $admin = $request->user();

        $updated = AuditLog::whereIn('id', $validated['ids'])
            ->where('user_id', $admin->id)
            ->update(['comment' => $validated['comment'] ?? null]);

        return response()->json(['updated' => $updated]);
    }

    public function updateComment(Request $request, AuditLog $auditLog): JsonResponse
    {
        Gate::authorize('comment', $auditLog);

        /** @var array{comment: string|null} $validated */
        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $auditLog->comment = $validated['comment'] ?? null;
        $auditLog->save();

        return response()->json(['comment' => $auditLog->comment]);
    }
}
