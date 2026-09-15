<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection(User::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->authorize('create', User::class);

        $data = $this->validated($request);
        $this->authorizeAssignedRole($actor, UserRole::from($data['role']));

        $user = User::query()->create([
            ...$data,
            'account_id' => $actor->account_id,
        ]);

        AuditLog::record(
            event: 'user.created',
            subject: $user,
            changes: ['email' => $user->email, 'role' => $user->role->value],
            userId: $actor->id,
            ip: $request->ip(),
        );

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(Request $request, User $user): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->authorize('update', $user);

        $data = $this->validated($request, $user);

        if (isset($data['role'])) {
            $this->authorizeAssignedRole($actor, UserRole::from($data['role']));
        }

        if ($actor->is($user) && ($data['is_active'] ?? true) === false) {
            abort(422, 'No puedes desactivar tu propio usuario.');
        }

        if ($this->wouldRemoveLastOwner($user, $data)) {
            abort(422, 'La cuenta debe conservar al menos un propietario activo.');
        }

        $changes = array_intersect_key($data, array_flip(['name', 'email', 'role', 'is_active']));
        $user->update($data);

        if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
            $user->tokens()->delete();
        }

        AuditLog::record(
            event: 'user.updated',
            subject: $user,
            changes: $changes,
            userId: $actor->id,
            ip: $request->ip(),
        );

        return new UserResource($user->fresh());
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->authorize('delete', $user);

        if ($actor->is($user)) {
            abort(422, 'No puedes eliminar tu propio usuario.');
        }

        AuditLog::record(
            event: 'user.deleted',
            subject: $user,
            changes: ['email' => $user->email, 'role' => $user->role->value],
            userId: $actor->id,
            ip: $request->ip(),
        );

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => [$user === null ? 'required' : 'sometimes', 'string', 'max:120'],
            'email' => [
                $user === null ? 'required' : 'sometimes',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [$user === null ? 'required' : 'sometimes', Password::min(8)],
            'role' => [$user === null ? 'required' : 'sometimes', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeAssignedRole(User $actor, UserRole $role): void
    {
        if ($role === UserRole::Owner && ! $actor->isOwner()) {
            abort(403, 'Solo un propietario puede asignar el rol de propietario.');
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function wouldRemoveLastOwner(User $user, array $changes): bool
    {
        if (! $user->isOwner() || ! $user->is_active) {
            return false;
        }

        $losesOwnerRole = isset($changes['role']) && $changes['role'] !== UserRole::Owner->value;
        $becomesInactive = array_key_exists('is_active', $changes) && $changes['is_active'] === false;

        if (! $losesOwnerRole && ! $becomesInactive) {
            return false;
        }

        return User::withoutGlobalScopes()
            ->where('account_id', $user->account_id)
            ->whereKeyNot($user->id)
            ->where('role', UserRole::Owner->value)
            ->where('is_active', true)
            ->doesntExist();
    }
}
