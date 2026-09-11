<?php

namespace App\Services\Rules;

use App\Domain\Enums\RuleKey;
use App\Models\Asset;
use App\Models\Check;
use App\Models\Client;
use App\Models\Evidence;
use App\Models\RuleSetting;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\Rules\BackupFailedRule;
use App\Services\Rules\Rules\BackupStaleRule;
use App\Services\Rules\Rules\CollectorFailingRule;
use App\Services\Rules\Rules\ContainerDownRule;
use App\Services\Rules\Rules\DiskUsageRule;
use App\Services\Rules\Rules\DockerUnhealthyRule;
use App\Services\Rules\Rules\GithubWorkflowFailedRule;
use App\Services\Rules\Rules\HighCpuRule;
use App\Services\Rules\Rules\HighMemoryRule;
use App\Services\Rules\Rules\PendingSecurityUpdatesRule;
use App\Services\Rules\Rules\ServerUnreachableRule;
use App\Services\Rules\Rules\SslExpiringRule;
use App\Services\Rules\Rules\WebsiteDownRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Evalua el catalogo de reglas sobre la ultima evidencia de cada activo.
 *
 * Anadir una regla es anadir una clase a RULES; no hay que tocar nada mas.
 */
class RulesEngine
{
    /** @var array<int, class-string<Rule>> */
    private const RULES = [
        WebsiteDownRule::class,
        SslExpiringRule::class,
        BackupFailedRule::class,
        BackupStaleRule::class,
        DiskUsageRule::class,
        ContainerDownRule::class,
        DockerUnhealthyRule::class,
        HighMemoryRule::class,
        HighCpuRule::class,
        PendingSecurityUpdatesRule::class,
        ServerUnreachableRule::class,
        GithubWorkflowFailedRule::class,
        CollectorFailingRule::class,
    ];

    /**
     * @return array<int, Rule>
     */
    public function rules(): array
    {
        return array_map(
            static fn (string $class): Rule => app($class),
            self::RULES,
        );
    }

    public function evaluateClient(Client $client): RuleContext
    {
        $context = $this->buildContext($client);

        return $this->evaluate($context);
    }

    public function buildContext(Client $client): RuleContext
    {
        $now = CarbonImmutable::now();

        $assets = Asset::withoutGlobalScopes()
            ->where('account_id', $client->account_id)
            ->where('client_id', $client->id)
            ->where('active', true)
            ->get();

        $checks = Check::withoutGlobalScopes()
            ->where('account_id', $client->account_id)
            ->where('client_id', $client->id)
            ->get();

        return new RuleContext(
            accountId: $client->account_id,
            clientId: $client->id,
            assets: $assets->keyBy('id'),
            checks: $checks,
            latest: $this->latestEvidence($client->id),
            now: $now,
            thresholdResolver: fn (int $accountId, ?int $clientId, RuleKey $rule): array
                => RuleSetting::resolveThresholds($accountId, $clientId, $rule),
        );
    }

    /**
     * Ultima evidencia de cada combinacion activo + tipo.
     *
     * DISTINCT ON es la forma mas barata de hacerlo en PostgreSQL: una sola
     * pasada sobre el indice, sin subconsultas por activo.
     *
     * @return Collection<string, Evidence>
     */
    private function latestEvidence(int $clientId): Collection
    {
        $rows = DB::select(
            'SELECT DISTINCT ON (asset_id, type) *
             FROM evidence
             WHERE client_id = ? AND collected_at >= ?
             ORDER BY asset_id, type, collected_at DESC, id DESC',
            [$clientId, CarbonImmutable::now()->subDays(90)],
        );

        return Evidence::hydrate(array_map(static fn ($row) => (array) $row, $rows))
            ->keyBy(fn (Evidence $evidence): string => $evidence->asset_id.':'.$evidence->type->value);
    }

    public function evaluate(RuleContext $context): RuleContext
    {
        $violations = [];

        foreach ($this->rules() as $rule) {
            if (! RuleSetting::isEnabled($context->accountId, $context->clientId, $rule->key())) {
                continue;
            }

            $violation = $rule->evaluate($context);

            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        $context->setViolations($violations);

        return $context;
    }
}
