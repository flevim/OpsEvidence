<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->dashboard->overview($request->user()->account_id);

        if ($request->filled('client_id')) {
            $client = Client::findOrFail($request->integer('client_id'));
            $this->authorize('view', $client);

            return response()->json($this->dashboard->clientOverview($client));
        }

        return response()->json($data);
    }
}
