<?php

namespace App\Models;

use App\Domain\Enums\IntegrationType;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    /** @use HasFactory<\Database\Factories\IntegrationFactory> */
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'client_id',
        'type',
        'name',
        'configuration',
        'credentials',
        'credentials_reference',
        'active',
        'last_sync_at',
        'last_success_at',
        'last_error',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'type' => IntegrationType::class,
            'configuration' => 'array',
            'credentials' => 'encrypted:array',
            'active' => 'boolean',
            'last_sync_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function hasCredentials(): bool
    {
        return filled($this->credentials);
    }

    public function markSynced(): void
    {
        $this->forceFill([
            'last_sync_at' => now(),
            'last_success_at' => now(),
            'last_error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'last_sync_at' => now(),
            'last_error' => $error,
        ])->save();
    }
}
