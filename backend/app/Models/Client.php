<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToAccount, HasFactory, SoftDeletes;

    protected $fillable = [
        'account_id',
        'name',
        'slug',
        'description',
        'contact_name',
        'contact_email',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $client): void {
            if (blank($client->slug)) {
                $client->slug = static::uniqueSlug($client->account_id, $client->name);
            }
        });
    }

    public static function uniqueSlug(?int $accountId, string $name): string
    {
        $base = Str::slug($name) ?: 'cliente';
        $slug = $base;
        $suffix = 2;

        while (static::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
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

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function dailySummaries(): HasMany
    {
        return $this->hasMany(DailySummary::class);
    }
}
