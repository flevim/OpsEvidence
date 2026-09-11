<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\AuditLogFactory> */
    use BelongsToAccount, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'changes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param array<string, mixed> $changes
     */
    public static function record(
        string $event,
        ?Model $subject = null,
        array $changes = [],
        ?int $accountId = null,
        ?int $userId = null,
        ?string $ip = null,
    ): self {
        $log = new self([
            'user_id' => $userId,
            'event' => $event,
            'auditable_type' => $subject !== null ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'ip_address' => $ip,
        ]);

        $log->account_id = $accountId ?? $subject?->account_id;
        $log->save();

        return $log;
    }
}
