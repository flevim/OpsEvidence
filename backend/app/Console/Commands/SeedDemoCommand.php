<?php

namespace App\Console\Commands;

use App\Domain\Enums\AccountPlan;
use App\Domain\Enums\AssetType;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\EnvironmentType;
use App\Domain\Enums\UserRole;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Check;
use App\Models\Client;
use App\Models\User;
use App\Support\AccountContext;
use Illuminate\Console\Command;

/**
 * Datos de demostracion idempotentes.
 *
 * Ejecutar varias veces no duplica nada: es lo que permite que
 * `docker compose up` siembre en el primer arranque sin sorpresas.
 */
class SeedDemoCommand extends Command
{
    protected $signature = 'opsevidence:seed-demo';

    protected $description = 'Crea datos de demostración (cuenta, clientes, activos y checks) de forma idempotente.';

    public function handle(): int
    {
        $account = Account::withoutGlobalScopes()->firstOrCreate(
            ['slug' => 'example-msp'],
            [
                'name' => 'Example MSP',
                'plan' => AccountPlan::Msp->value,
                'client_limit' => AccountPlan::Msp->clientLimit(),
                'settings' => ['brand_name' => 'Example MSP'],
            ],
        );

        AccountContext::run($account->id, function () use ($account): void {
            $this->seedUsers($account);
            $this->seedClients($account);
        });

        $this->info('Datos de demostración listos.');

        return self::SUCCESS;
    }

    private function seedUsers(Account $account): void
    {
        $users = [
            ['name' => 'Ana Rojas', 'email' => 'owner@opsevidence.test', 'role' => UserRole::Owner],
            ['name' => 'Bruno Díaz', 'email' => 'tecnico@opsevidence.test', 'role' => UserRole::Technician],
            ['name' => 'Carla Soto', 'email' => 'lector@opsevidence.test', 'role' => UserRole::Viewer],
        ];

        foreach ($users as $data) {
            User::withoutGlobalScopes()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'account_id' => $account->id,
                    'name' => $data['name'],
                    'password' => 'password',
                    'role' => $data['role']->value,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedClients(Account $account): void
    {
        $clients = [
            [
                'name' => 'Acme Ltd.',
                'description' => 'Cliente con hosting y aplicación propia.',
                'environments' => [
                    ['name' => 'Producción', 'type' => EnvironmentType::Production],
                ],
                'assets' => [
                    ['name' => 'web-server-01', 'type' => AssetType::Server, 'hostname' => 'web-server-01', 'address' => '10.0.1.10'],
                    ['name' => 'db-server-01', 'type' => AssetType::Server, 'hostname' => 'db-server-01', 'address' => '10.0.1.20'],
                    ['name' => 'api.acme.test', 'type' => AssetType::Website, 'address' => 'https://api.acme.test'],
                    ['name' => 'backup-production', 'type' => AssetType::BackupSource, 'hostname' => 'db-server-01'],
                ],
            ],
            [
                'name' => 'Example Store',
                'description' => 'Tienda en línea con servidores propios.',
                'environments' => [
                    ['name' => 'Producción', 'type' => EnvironmentType::Production],
                ],
                'assets' => [
                    ['name' => 'tienda-web-01', 'type' => AssetType::Server, 'hostname' => 'tienda-web-01'],
                    ['name' => 'www.example-store.test', 'type' => AssetType::Website, 'address' => 'https://www.example-store.test'],
                ],
            ],
            [
                'name' => 'Example SaaS',
                'description' => 'Producto SaaS con contenedores y despliegues continuos.',
                'environments' => [
                    ['name' => 'Producción', 'type' => EnvironmentType::Production],
                    ['name' => 'Staging', 'type' => EnvironmentType::Staging],
                ],
                'assets' => [
                    ['name' => 'container-host-01', 'type' => AssetType::ContainerHost, 'hostname' => 'container-host-01'],
                    ['name' => 'app.example-saas.test', 'type' => AssetType::Website, 'address' => 'https://app.example-saas.test'],
                    ['name' => 'example-saas/backend', 'type' => AssetType::Repository, 'hostname' => 'github.com'],
                ],
            ],
        ];

        foreach ($clients as $definition) {
            $client = Client::withoutGlobalScopes()->firstOrCreate(
                ['account_id' => $account->id, 'slug' => \Illuminate\Support\Str::slug($definition['name'])],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'contact_name' => 'Contacto '.$definition['name'],
                    'contact_email' => 'contacto@'.str_replace('.', '', \Illuminate\Support\Str::slug($definition['name'])).'.test',
                    'active' => true,
                ],
            );

            $environments = [];

            foreach ($definition['environments'] as $environment) {
                $environments[$environment['name']] = $client->environments()->firstOrCreate(
                    ['name' => $environment['name']],
                    ['account_id' => $account->id, 'type' => $environment['type']->value],
                );
            }

            foreach ($definition['assets'] as $assetData) {
                $asset = Asset::withoutGlobalScopes()->firstOrCreate(
                    ['client_id' => $client->id, 'name' => $assetData['name']],
                    [
                        'account_id' => $account->id,
                        'environment_id' => $environments['Producción']?->id,
                        'type' => $assetData['type']->value,
                        'hostname' => $assetData['hostname'] ?? null,
                        'address' => $assetData['address'] ?? null,
                        'active' => true,
                    ],
                );

                $this->seedChecks($account, $client, $asset);
            }
        }
    }

    private function seedChecks(Account $account, Client $client, Asset $asset): void
    {
        foreach ($asset->type->suggestedCheckTypes() as $type) {
            if ($type === CheckType::GithubWorkflow) {
                continue;
            }

            Check::withoutGlobalScopes()->firstOrCreate(
                ['asset_id' => $asset->id, 'type' => $type->value, 'name' => $type->label()],
                [
                    'account_id' => $account->id,
                    'client_id' => $client->id,
                    'configuration' => $this->configurationFor($type, $asset),
                    'interval_seconds' => $type->defaultIntervalSeconds(),
                    'freshness_ttl_seconds' => $type->defaultFreshnessTtlSeconds(),
                    'enabled' => true,
                    'next_run_at' => now(),
                ],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function configurationFor(CheckType $type, Asset $asset): array
    {
        return match ($type) {
            CheckType::HttpStatus, CheckType::HttpResponseTime, CheckType::SslExpiration => [
                'url' => $asset->address ?? 'https://'.$asset->hostname,
            ],
            default => [],
        };
    }
}
