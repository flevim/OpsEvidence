<?php

use App\Domain\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('crea la cuenta y su primer propietario', function () {
    $this->artisan('opsevidence:create-owner', [
        '--account' => 'Piloto MSP',
        '--name' => 'Dueña Piloto',
        '--email' => 'owner@piloto.test',
        '--password' => 'una-clave-muy-segura',
    ])->assertSuccessful();

    $account = Account::query()->sole();
    $user = User::query()->sole();

    expect($account->name)->toBe('Piloto MSP')
        ->and($user->account_id)->toBe($account->id)
        ->and($user->role)->toBe(UserRole::Owner)
        ->and(Hash::check('una-clave-muy-segura', $user->password))->toBeTrue();
});

it('rechaza contraseñas débiles sin crear datos parciales', function () {
    $this->artisan('opsevidence:create-owner', [
        '--account' => 'Piloto MSP',
        '--name' => 'Dueña Piloto',
        '--email' => 'owner@piloto.test',
        '--password' => 'corta',
    ])->assertFailed();

    expect(Account::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});
