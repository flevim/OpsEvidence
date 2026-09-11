<?php

namespace App\Models;

use App\Domain\Enums\AssetType;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    /** @use HasFactory<\Database\Factories\AssetFactory> */
    use BelongsToAccount, HasFactory, SoftDeletes;

    protected $fillable = [
        'account_id',
        'client_id',
        'environment_id',
        'name',
        'type',
        'hostname',
        'address',
        'provider',
        'metadata',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'metadata' => 'array',
            'active' => 'boolean',
            'last_evidence_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Ultima evidencia de cualquier tipo, para la vista de detalle.
     */
    public function latestEvidence(): HasOne
    {
        return $this->hasOne(Evidence::class)->latestOfMany('collected_at');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOfType($query, AssetType $type)
    {
        return $query->where('type', $type->value);
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}
