<?php

namespace App\Models;

use App\Domain\Enums\EnvironmentType;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Environment extends Model
{
    /** @use HasFactory<\Database\Factories\EnvironmentFactory> */
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'client_id',
        'name',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => EnvironmentType::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
