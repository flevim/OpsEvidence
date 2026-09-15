<?php

namespace App\Models;

use App\Domain\Enums\UserRole;
use App\Models\Concerns\BelongsToAccount;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property int $account_id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property bool $is_active
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToAccount, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'account_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isViewer(): bool
    {
        return $this->role === UserRole::Viewer;
    }

    /**
     * @param  array<int, UserRole>|UserRole  $roles
     */
    public function hasRole(UserRole|array $roles): bool
    {
        return in_array($this->role, is_array($roles) ? $roles : [$roles], true);
    }

    public function atLeast(UserRole $role): bool
    {
        return $this->role->level() >= $role->level();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
