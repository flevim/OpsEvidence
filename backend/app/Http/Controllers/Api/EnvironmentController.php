<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\EnvironmentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\EnvironmentResource;
use App\Models\Client;
use App\Models\Environment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnvironmentController extends Controller
{
    public function index(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        return response()->json([
            'data' => EnvironmentResource::collection(
                $client->environments()->withCount('assets')->orderBy('name')->get(),
            ),
        ]);
    }

    public function store(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('environments')->where('client_id', $client->id)],
            'type' => ['required', Rule::in(EnvironmentType::values())],
        ]);

        $environment = $client->environments()->create($data);

        return (new EnvironmentResource($environment->loadCount('assets')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Environment $environment): JsonResponse
    {
        $this->authorize('update', $environment);

        $data = $request->validate([
            'name' => [
                'sometimes', 'string', 'max:80',
                Rule::unique('environments')->where('client_id', $environment->client_id)->ignore($environment->id),
            ],
            'type' => ['sometimes', Rule::in(EnvironmentType::values())],
        ]);

        $environment->update($data);

        return (new EnvironmentResource($environment->fresh()->loadCount('assets')))->response();
    }

    public function destroy(Environment $environment): JsonResponse
    {
        $this->authorize('delete', $environment);

        $environment->delete();

        return response()->json(['message' => 'Entorno eliminado.']);
    }
}
