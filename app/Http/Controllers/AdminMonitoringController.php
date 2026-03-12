<?php

namespace App\Http\Controllers;

use App\Support\MonitoringService;
use Illuminate\Http\JsonResponse;

class AdminMonitoringController extends Controller
{
    public function index(MonitoringService $monitoringService): JsonResponse
    {
        return response()->json([
            'monitoring' => $monitoringService->getDashboard(),
        ]);
    }
}
