<?php

namespace App\Models;

use App\Domain\Enums\TokenAbility;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\ApiTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Token de agente o integracion.
 *
 * El valor en claro solo existe en memoria durante la emision: en base de datos
 * unicamente se guarda su hash SHA-256 (ver docs/security.md).
 */
class ApiToken extends Model
{
    /** @use HasFactory<ApiTokenFactory> */
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'client_id',
        'asset_id',
        'name',
        'token_hash',
        'prefix',
        'abilities',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @param  array<int, TokenAbility>  $abilities
     * @return array{0: string, 1: self}
     */
    public static function issue(
        Account $account,
        string $name,
        array $abilities = [TokenAbility::EvidenceWrite],
        ?int $clientId = null,
        ?int $assetId = null,
        ?\DateTimeInterface $expiresAt = null,
        ?int $createdBy = null,
    ): array {
        $plain = 'ops_'.Str::random(48);

        $token = new self([
            'client_id' => $clientId,
            'asset_id' => $assetId,
            'name' => $name,
            'token_hash' => self::hash($plain),
            'prefix' => substr($plain, 0, 12),
            'abilities' => array_map(static fn (TokenAbility $a): string => $a->value, $abilities),
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);

        $token->account_id = $account->id;
        $token->save();

        return [$plain, $token];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function findByPlainText(string $plain): ?self
    {
        return static::withoutGlobalScopes()
            ->where('token_hash', self::hash($plain))
            ->first();
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function hasAbility(TokenAbility $ability): bool
    {
        return in_array($ability->value, $this->abilities ?? [], true);
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    public function registerUsage(?string $ip): void
    {
        $this->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ])->saveQuietly();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
