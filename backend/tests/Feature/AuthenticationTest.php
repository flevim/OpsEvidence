<?php

use App\Models\Account;
use App\Models\User;

it('inicia sesión con credenciales válidas y devuelve un token', function () {
    $account = Account::factory()->create();
    User::factory()->forAccount($account)->create([
        'email' => 'ana@example.test',
        'password' => 'clave-segura-123',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'ana@example.test',
        'password' => 'clave-segura-123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name', 'email', 'role', 'account' => ['id', 'name']]]);
});

it('normaliza el email a minúsculas al iniciar sesión', function () {
    $account = Account::factory()->create();
    User::factory()->forAccount($account)->create([
        'email' => 'ana@example.test',
        'password' => 'clave-segura-123',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'ANA@Example.Test',
        'password' => 'clave-segura-123',
    ])->assertOk();
});

it('rechaza una contraseña incorrecta sin revelar si el email existe', function () {
    $account = Account::factory()->create();
    User::factory()->forAccount($account)->create([
        'email' => 'ana@example.test',
        'password' => 'clave-segura-123',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'ana@example.test',
        'password' => 'incorrecta',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    $this->postJson('/api/auth/login', [
        'email' => 'noexiste@example.test',
        'password' => 'incorrecta',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('rechaza usuarios desactivados', function () {
    $account = Account::factory()->create();
    User::factory()->forAccount($account)->inactive()->create([
        'email' => 'inactivo@example.test',
        'password' => 'clave-segura-123',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'inactivo@example.test',
        'password' => 'clave-segura-123',
    ])->assertStatus(422);
});

it('aplica rate limiting al inicio de sesión', function () {
    $account = Account::factory()->create();
    User::factory()->forAccount($account)->create([
        'email' => 'limite@example.test',
        'password' => 'clave-segura-123',
    ]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/auth/login', [
            'email' => 'limite@example.test',
            'password' => 'incorrecta',
        ])->assertStatus(422);
    }

    $this->postJson('/api/auth/login', [
        'email' => 'limite@example.test',
        'password' => 'incorrecta',
    ])->assertStatus(429);
});

it('exige autenticación en la API protegida', function () {
    $this->getJson('/api/clients')->assertUnauthorized();
    $this->getJson('/api/dashboard')->assertUnauthorized();
    $this->getJson('/api/reports')->assertUnauthorized();
});

it('expone el endpoint de salud sin autenticación', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure(['status', 'version', 'checks' => ['database' => ['ok'], 'redis' => ['ok']]]);
});

it('cierra la sesión revocando el token actual', function () {
    $account = Account::factory()->create();
    $user = User::factory()->forAccount($account)->create();

    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/auth/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

it('devuelve el usuario autenticado con su cuenta', function () {
    ['user' => $user] = actingAsAccount();

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.account.id', $user->account_id);
});
