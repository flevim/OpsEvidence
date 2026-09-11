<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;

class ClientSummaryController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        return response()->json($this->dashboard->clientOverview($client));
    }
}
