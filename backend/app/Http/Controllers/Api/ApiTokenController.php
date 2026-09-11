<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Emision y revocacion de tokens de agente.
 *
 * El valor en claro se devuelve UNA sola vez, en la respuesta de creacion;
 * despues solo queda su hash y su prefijo (ver docs/security.md).
 */
class ApiTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApiToken::class);

        $tokens = ApiToken::query()
            ->with(['client:id,name', 'asset:id,name'])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApiToken $token): array => $this->present($token));

        return response()->json(['data' => $tokens]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ApiToken::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => [Rule::in(TokenAbility::values())],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $account = $request->user()->account;

        if (isset($data['client_id'])) {
            $this->authorize('view', Client::findOrFail($data['client_id']));
        }

        if (isset($data['asset_id'])) {
            $this->authorize('view', Asset::findOrFail($data['asset_id']));
        }

        $abilities = array_map(
            fn (string $ability): TokenAbility => TokenAbility::from($ability),
            $data['abilities'] ?? [TokenAbility::EvidenceWrite->value],
        );

        [$plain, $token] = ApiToken::issue(
            account: $account,
            name: $data['name'],
            abilities: $abilities,
            clientId: $data['client_id'] ?? null,
            assetId: $data['asset_id'] ?? null,
            expiresAt: isset($data['expires_at']) ? \Carbon\CarbonImmutable::parse($data['expires_at']) : null,
            createdBy: $request->user()->id,
        );

        AuditLog::record(
            event: 'token.issued',
            subject: $token,
            changes: ['name' => $token->name, 'client_id' => $token->client_id],
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        return response()->json([
            ...$this->present($token),
            'token' => $plain,
            'warning' => 'Guarda este token ahora: no volverá a mostrarse.',
        ], 201);
    }

    public function destroy(Request $request, ApiToken $api_token): JsonResponse
    {
        $this->authorize('delete', $api_token);

        $api_token->revoke();

        AuditLog::record(
            event: 'token.revoked',
            subject: $api_token,
            changes: ['name' => $api_token->name],
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        return response()->json(['message' => 'Token revocado.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ApiToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'prefix' => $token->prefix,
            'client_id' => $token->client_id,
            'client' => $token->relationLoaded('client') ? $token->client?->name : null,
            'asset_id' => $token->asset_id,
            'asset' => $token->relationLoaded('asset') ? $token->asset?->name : null,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'revoked_at' => $token->revoked_at?->toIso8601String(),
            'usable' => $token->isUsable(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }
}
