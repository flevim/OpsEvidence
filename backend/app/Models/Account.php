<?php

namespace App\Models;

use App\Domain\Enums\AccountPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Account extends Model
{
    /** @use HasFactory<\Database\Factories\AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'status',
        'client_limit',
        'settings',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'plan' => AccountPlan::class,
            'settings' => 'array',
            'client_limit' => 'integer',
            'trial_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $account): void {
            if (blank($account->slug)) {
                $account->slug = static::uniqueSlug($account->name);
            }

            if (blank($account->client_limit) && $account->plan instanceof AccountPlan) {
                $account->client_limit = $account->plan->clientLimit();
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'cuenta';
        $slug = $base;
        $suffix = 2;

        while (static::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'report_locale' => data_get($this->settings, 'report_locale', 'es'),
            'report_timezone' => data_get($this->settings, 'report_timezone', 'UTC'),
            'brand_name' => data_get($this->settings, 'brand_name', $this->name),
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canAddClient(): bool
    {
        if ($this->client_limit === null) {
            return true;
        }

        return $this->clients()->count() < $this->client_limit;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
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

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function ruleSettings(): HasMany
    {
        return $this->hasMany(RuleSetting::class);
    }
}
