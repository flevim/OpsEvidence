<?php

namespace App\Models;

use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\IncidentStatus;
use App\Domain\Enums\RuleKey;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    /** @use HasFactory<\Database\Factories\IncidentFactory> */
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'client_id',
        'asset_id',
        'rule_key',
        'signature',
        'severity',
        'status',
        'title',
        'description',
        'evidence_id',
        'opened_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'resolution_note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'rule_key' => RuleKey::class,
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'metadata' => 'array',
            'opened_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public static function signatureFor(RuleKey $rule, ?int $assetId): string
    {
        return $rule->value.':'.($assetId ?? 'global');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function acknowledge(int $userId): void
    {
        if ($this->status === IncidentStatus::Open) {
            $this->forceFill([
                'status' => IncidentStatus::Acknowledged,
                'acknowledged_at' => now(),
                'acknowledged_by' => $userId,
            ])->save();
        }
    }

    public function resolve(int $userId, ?string $note = null): void
    {
        $this->forceFill([
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution_note' => $note,
        ])->save();
    }

    public function ignore(int $userId, ?string $note = null): void
    {
        $this->forceFill([
            'status' => IncidentStatus::Ignored,
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution_note' => $note,
        ])->save();
    }

    public function durationSeconds(): ?int
    {
        if ($this->resolved_at === null) {
            return null;
        }

        return (int) $this->opened_at->diffInSeconds($this->resolved_at);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            IncidentStatus::Open->value,
            IncidentStatus::Acknowledged->value,
        ]);
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }
}
