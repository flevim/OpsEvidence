<?php

namespace App\Models;

use App\Domain\Enums\CheckRunStatus;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckRun extends Model
{
    /** @use HasFactory<\Database\Factories\CheckRunFactory> */
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'check_id',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => CheckRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function finish(CheckRunStatus $status, ?string $error = null): void
    {
        $this->forceFill([
            'status' => $status,
            'finished_at' => now(),
            'duration_ms' => $this->started_at
                ? (int) round(abs(now()->getTimestampMs() - $this->started_at->getTimestampMs()))
                : null,
            'error_message' => $error,
        ])->save();
    }
}
