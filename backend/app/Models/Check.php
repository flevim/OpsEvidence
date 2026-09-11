<?php

namespace App\Models;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Check extends Model
{
    /** @use HasFactory<\Database\Factories\CheckFactory> */
    use BelongsToAccount, HasFactory;

    public const FRESH = 'fresh';

    public const STALE = 'stale';

    public const NEVER_COLLECTED = 'never_collected';

    protected $fillable = [
        'account_id',
        'client_id',
        'asset_id',
        'type',
        'name',
        'configuration',
        'interval_seconds',
        'freshness_ttl_seconds',
        'enabled',
        'last_run_at',
        'last_success_at',
        'last_status',
        'last_error',
        'consecutive_failures',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CheckType::class,
            'configuration' => 'array',
            'interval_seconds' => 'integer',
            'freshness_ttl_seconds' => 'integer',
            'enabled' => 'boolean',
            'consecutive_failures' => 'integer',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_status' => EvidenceStatus::class,
            'next_run_at' => 'datetime',
        ];
    }

    /**
     * Estado de frescura de los datos de este check.
     *
     * Es la pieza que impide mostrar un cero cuando en realidad no hay datos
     * (ver docs/product.md 5.4).
     */
    public function freshness(): string
    {
        if ($this->last_success_at === null) {
            return self::NEVER_COLLECTED;
        }

        $expiresAt = $this->last_success_at->copy()->addSeconds($this->freshness_ttl_seconds);

        return $expiresAt->isFuture() ? self::FRESH : self::STALE;
    }

    public function hasData(): bool
    {
        return $this->last_success_at !== null;
    }

    public function markSuccess(EvidenceStatus $status, ?\DateTimeInterface $at = null): void
    {
        $at = $at ?? now();

        $this->forceFill([
            'last_run_at' => $at,
            'last_success_at' => $at,
            'last_status' => $status,
            'last_error' => null,
            'consecutive_failures' => 0,
            'next_run_at' => now()->addSeconds($this->interval_seconds),
        ])->save();
    }

    public function markFailure(string $error, ?\DateTimeInterface $at = null): void
    {
        $at = $at ?? now();

        $this->forceFill([
            'last_run_at' => $at,
            'last_status' => EvidenceStatus::Failed,
            'last_error' => $error,
            'consecutive_failures' => $this->consecutive_failures + 1,
            'next_run_at' => now()->addSeconds($this->interval_seconds),
        ])->save();
    }

    public function nextRunInSeconds(): int
    {
        return max($this->interval_seconds, (int) config('opsevidence.limits.min_check_interval_seconds', 60));
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CheckRun::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('enabled', true)
            ->where(function (Builder $inner): void {
                $inner->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            });
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }
}
