<?php

namespace App\Models;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceSource;
use App\Domain\Enums\EvidenceStatus;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\EvidenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Evidencia: registro append-only de un hecho observado.
 * Las filas nunca se modifican (ver docs/evidence-model.md).
 */
class Evidence extends Model
{
    /** @use HasFactory<EvidenceFactory> */
    use BelongsToAccount, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'client_id',
        'asset_id',
        'check_id',
        'check_run_id',
        'type',
        'status',
        'raw_status',
        'title',
        'value_text',
        'value_numeric',
        'unit',
        'data',
        'raw_data',
        'source',
        'collected_at',
        'dedup_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => CheckType::class,
            'status' => EvidenceStatus::class,
            'source' => EvidenceSource::class,
            'data' => 'array',
            'raw_data' => 'array',
            'value_numeric' => 'float',
            'collected_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('La evidencia es inmutable: no puede modificarse una vez registrada.');
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    public function checkRun(): BelongsTo
    {
        return $this->belongsTo(CheckRun::class);
    }

    /**
     * Clave de idempotencia: mismo check, mismo tipo y mismo minuto => misma fila.
     */
    public static function dedupKey(?int $checkId, CheckType $type, \DateTimeInterface $collectedAt, string $discriminator = ''): string
    {
        $bucket = (int) floor($collectedAt->getTimestamp() / 60);

        return hash('sha256', implode('|', [
            $checkId ?? 'manual',
            $type->value,
            (string) $bucket,
            $discriminator,
        ]));
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeForAsset(Builder $query, int $assetId): Builder
    {
        return $query->where('asset_id', $assetId);
    }

    public function scopeOfType(Builder $query, CheckType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeWithStatus(Builder $query, EvidenceStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeCollectedBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('collected_at', [$from, $to]);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('collected_at')->orderByDesc('id');
    }
}
