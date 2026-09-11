<?php

namespace App\Models;

use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleSetting extends Model
{
    /** @use HasFactory<\Database\Factories\RuleSettingFactory> */
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'client_id',
        'rule_key',
        'enabled',
        'thresholds',
        'severity',
    ];

    protected function casts(): array
    {
        return [
            'rule_key' => RuleKey::class,
            'enabled' => 'boolean',
            'thresholds' => 'array',
            'severity' => IncidentSeverity::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Resuelve los thresholds efectivos: cliente > cuenta > valores por defecto del catalogo.
     *
     * @return array<string, mixed>
     */
    public static function resolveThresholds(int $accountId, ?int $clientId, RuleKey $rule): array
    {
        $setting = static::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('rule_key', $rule->value)
            ->when(
                $clientId !== null,
                fn ($query) => $query->where('client_id', $clientId),
                fn ($query) => $query->whereNull('client_id'),
            )
            ->first();

        if ($setting?->thresholds) {
            return array_merge($rule->defaultThresholds(), $setting->thresholds);
        }

        if ($clientId !== null) {
            return static::resolveThresholds($accountId, null, $rule);
        }

        return $rule->defaultThresholds();
    }

    public static function isEnabled(int $accountId, ?int $clientId, RuleKey $rule): bool
    {
        $setting = static::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('rule_key', $rule->value)
            ->when(
                $clientId !== null,
                fn ($query) => $query->where('client_id', $clientId),
                fn ($query) => $query->whereNull('client_id'),
            )
            ->first();

        if ($setting !== null) {
            return $setting->enabled;
        }

        if ($clientId !== null) {
            return static::isEnabled($accountId, null, $rule);
        }

        return true;
    }
}
