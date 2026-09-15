<?php

namespace App\Console\Commands;

use App\Domain\Enums\AccountPlan;
use App\Domain\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('opsevidence:create-owner {--account=} {--name=} {--email=} {--password=}')]
#[Description('Crea una cuenta y su primer propietario sin cargar datos de demostración.')]
class CreateOwnerCommand extends Command
{
    public function handle(): int
    {
        $data = [
            'account' => $this->option('account') ?: $this->ask('Nombre de la cuenta'),
            'name' => $this->option('name') ?: $this->ask('Nombre del propietario'),
            'email' => $this->option('email') ?: $this->ask('Correo del propietario'),
            'password' => $this->option('password') ?: $this->secret('Contraseña (mínimo 12 caracteres)'),
        ];

        $validator = Validator::make($data, [
            'account' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        /** @var array{account: string, name: string, email: string, password: string} $validated */
        $validated = $validator->validated();

        $user = DB::transaction(function () use ($validated): User {
            $plan = AccountPlan::Freelancer;
            $account = Account::query()->create([
                'name' => $validated['account'],
                'plan' => $plan,
                'client_limit' => $plan->clientLimit(),
            ]);

            return User::query()->create([
                'account_id' => $account->id,
                'name' => $validated['name'],
                'email' => mb_strtolower($validated['email']),
                'password' => $validated['password'],
                'role' => UserRole::Owner,
                'is_active' => true,
            ]);
        });

        $this->info("Cuenta creada. Ya puedes entrar con {$user->email}.");

        return self::SUCCESS;
    }
}
