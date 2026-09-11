<?php

namespace App\Models;

use App\Domain\Enums\ActivityType;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'client_id',
        'asset_id',
        'user_id',
        'type',
        'title',
        'description',
        'performed_at',
        'billable',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'performed_at' => 'datetime',
            'billable' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('performed_at', [$from, $to]);
    }
}
