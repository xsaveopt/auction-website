<?php

namespace App\Http\Controllers;

use App\Support\StatsService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function index(StatsService $statsService): JsonResponse
    {
        return response()->json([
            'stats' => $statsService->getStats(),
        ]);
    }
}
