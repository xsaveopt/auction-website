<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
