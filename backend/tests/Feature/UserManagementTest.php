<?php

use App\Domain\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('lista solo los usuarios de la cuenta activa', function () {
    ['account' => $account] = actingAsAccount(UserRole::Admin);
    User::factory()->forAccount($account, UserRole::Technician)->create(['name' => 'Técnico propio']);
    User::factory()->forAccount(Account::factory()->create(), UserRole::Admin)->create(['name' => 'Admin ajeno']);

    $this->getJson('/api/users')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissing(['name' => 'Admin ajeno']);
});

it('permite al owner crear actualizar y eliminar usuarios', function () {
    actingAsAccount(UserRole::Owner);

    $created = $this->postJson('/api/users', [
        'name' => 'Ana Técnica',
        'email' => 'ana@example.test',
        'password' => 'segura-1234',
        'role' => UserRole::Technician->value,
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'ana@example.test')
        ->assertJsonPath('data.role', UserRole::Technician->value);

    $user = User::findOrFail($created->json('data.id'));
    expect(Hash::check('segura-1234', $user->password))->toBeTrue();
    $user->createToken('web');

    $this->patchJson('/api/users/'.$user->id, [
        'role' => UserRole::Admin->value,
        'is_active' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.role', UserRole::Admin->value)
        ->assertJsonPath('data.is_active', false);
    expect($user->tokens()->count())->toBe(0);

    $this->deleteJson('/api/users/'.$user->id)->assertOk();
    expect(User::withTrashed()->findOrFail($user->id)->trashed())->toBeTrue();
});

it('impide que un admin asigne el rol owner', function () {
    ['account' => $account] = actingAsAccount(UserRole::Admin);
    $target = User::factory()->forAccount($account, UserRole::Technician)->create();

    $this->postJson('/api/users', [
        'name' => 'Owner indebido',
        'email' => 'owner-indebido@example.test',
        'password' => 'segura-1234',
        'role' => UserRole::Owner->value,
    ])->assertForbidden();

    $this->patchJson('/api/users/'.$target->id, [
        'role' => UserRole::Owner->value,
    ])->assertForbidden();
});

it('protege al último owner activo', function () {
    ['user' => $owner] = actingAsAccount(UserRole::Owner);

    $this->patchJson('/api/users/'.$owner->id, ['role' => UserRole::Admin->value])
        ->assertUnprocessable();
    $this->patchJson('/api/users/'.$owner->id, ['is_active' => false])
        ->assertUnprocessable();
    $this->deleteJson('/api/users/'.$owner->id)->assertForbidden();
});

it('no permite administrar usuarios sin privilegios', function (UserRole $role) {
    actingAsAccount($role);

    $this->postJson('/api/users', [
        'name' => 'Sin permiso',
        'email' => 'sin-permiso@example.test',
        'password' => 'segura-1234',
        'role' => UserRole::Viewer->value,
    ])->assertForbidden();
})->with([UserRole::Technician, UserRole::Viewer]);

it('devuelve 404 al intentar administrar un usuario de otra cuenta', function () {
    actingAsAccount(UserRole::Owner);
    $foreign = User::factory()->forAccount(Account::factory()->create(), UserRole::Technician)->create();

    $this->patchJson('/api/users/'.$foreign->id, ['name' => 'Intruso'])->assertNotFound();
    $this->deleteJson('/api/users/'.$foreign->id)->assertNotFound();
});
