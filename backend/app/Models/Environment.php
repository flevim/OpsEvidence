<?php

namespace App\Models;

use App\Domain\Enums\EnvironmentType;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\EnvironmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $client_id
 * @property string $name
 * @property EnvironmentType $type
 */
class Environment extends Model
{
    /** @use HasFactory<EnvironmentFactory> */
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
