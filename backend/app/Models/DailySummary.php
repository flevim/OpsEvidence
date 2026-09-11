<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySummary extends Model
{
    /** @use HasFactory<\Database\Factories\DailySummaryFactory> */
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'client_id',
        'date',
        'metrics',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'metrics' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
