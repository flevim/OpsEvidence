<?php

namespace App\Services\Collectors;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\IntegrationType;
use App\Models\Check;
use App\Models\Integration;
use App\Services\Collectors\Contracts\Collector;
use App\Services\Collectors\Exceptions\CollectionFailed;
use App\Services\Evidence\EvidenceNormalizer;
use App\Services\Evidence\EvidencePayload;
use Illuminate\Support\Facades\Http;

/**
 * Ultimo workflow de GitHub Actions de un repositorio.
 *
 * Se mantiene deliberadamente simple: ultimo run, su estado y su commit.
 */
class GithubWorkflowCollector implements Collector
{
    public function __construct(
        private readonly EvidenceNormalizer $normalizer,
    ) {
    }

    public function supports(): array
    {
        return [CheckType::GithubWorkflow];
    }

    public function collect(Check $check): array
    {
        $owner = $check->configuration['owner'] ?? null;
        $repo = $check->configuration['repo'] ?? null;
        $branch = $check->configuration['branch'] ?? null;
        $workflow = $check->configuration['workflow'] ?? null;

        if (blank($owner) || blank($repo)) {
            throw new CollectionFailed('El check de GitHub requiere "owner" y "repo" en su configuración.');
        }

        $request = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => config('opsevidence.collectors.http.user_agent'),
        ])->timeout((int) config('opsevidence.collectors.http.timeout_seconds'));

        if ($token = $this->tokenFor($check)) {
            $request = $request->withToken($token);
        }

        $query = array_filter([
            'per_page' => 1,
            'branch' => $branch,
        ]);

        $url = sprintf('https://api.github.com/repos/%s/%s/actions/%s', $owner, $repo, $workflow ? 'workflows/'.$workflow.'/runs' : 'runs');

        $response = $request->get($url, $query);

        if ($response->failed()) {
            return [
                EvidencePayload::make(
                    type: CheckType::GithubWorkflow,
                    status: EvidenceStatus::Failed,
                    title: 'No se pudo consultar GitHub',
                    options: [
                        'raw_status' => 'api_error_'.$response->status(),
                        'data' => ['owner' => $owner, 'repo' => $repo, 'status_code' => $response->status()],
                        'raw_data' => ['owner' => $owner, 'repo' => $repo, 'status_code' => $response->status()],
                        'discriminator' => $owner.'/'.$repo,
                    ],
                ),
            ];
        }

        $runs = $response->json('workflow_runs') ?? [];

        if ($runs === []) {
            return [
                EvidencePayload::make(
                    type: CheckType::GithubWorkflow,
                    status: EvidenceStatus::Unknown,
                    title: 'El repositorio no tiene ejecuciones de workflow',
                    options: [
                        'raw_status' => 'no_runs',
                        'data' => ['owner' => $owner, 'repo' => $repo],
                        'raw_data' => ['owner' => $owner, 'repo' => $repo],
                        'discriminator' => $owner.'/'.$repo,
                    ],
                ),
            ];
        }

        $run = $runs[0];
        $status = $this->normalizer->fromGithubConclusion($run['conclusion'] ?? null, $run['status'] ?? null);

        return [
            EvidencePayload::make(
                type: CheckType::GithubWorkflow,
                status: $status,
                title: $this->title($run),
                options: [
                    'raw_status' => (string) ($run['conclusion'] ?? $run['status'] ?? 'unknown'),
                    'value_text' => $run['head_branch'] ?? null,
                    'data' => [
                        'owner' => $owner,
                        'repo' => $repo,
                        'workflow_name' => $run['name'] ?? null,
                        'conclusion' => $run['conclusion'] ?? null,
                        'status' => $run['status'] ?? null,
                        'branch' => $run['head_branch'] ?? null,
                        'sha' => $run['head_sha'] ?? null,
                        'short_sha' => isset($run['head_sha']) ? substr((string) $run['head_sha'], 0, 7) : null,
                        'html_url' => $run['html_url'] ?? null,
                        'run_started_at' => $run['run_started_at'] ?? null,
                        'updated_at' => $run['updated_at'] ?? null,
                        'actor' => $run['actor']['login'] ?? null,
                    ],
                    'raw_data' => $run,
                    'discriminator' => $owner.'/'.$repo,
                ],
            ),
        ];
    }

    /**
     * @param array<string, mixed> $run
     */
    private function title(array $run): string
    {
        $branch = $run['head_branch'] ?? 'rama desconocida';
        $conclusion = $run['conclusion'] ?? $run['status'] ?? 'desconocido';

        return match ($conclusion) {
            'success' => "El último despliegue en {$branch} fue exitoso",
            'failure', 'timed_out', 'startup_failure' => "El último despliegue en {$branch} falló",
            'cancelled' => "El último despliegue en {$branch} fue cancelado",
            'in_progress', 'queued', 'requested' => "Hay un despliegue en curso en {$branch}",
            default => "Último workflow en {$branch}: {$conclusion}",
        };
    }

    private function tokenFor(Check $check): ?string
    {
        $integration = Integration::query()
            ->where('client_id', $check->client_id)
            ->where('type', IntegrationType::Github->value)
            ->where('active', true)
            ->first();

        $credentials = $integration?->credentials ?? [];

        return $credentials['token'] ?? null;
    }
}
