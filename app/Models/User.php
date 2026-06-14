<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'phone',
        'first_name',
        'last_name',
        'avatar_path',
        'role',
        'label',
        'show_name',
        'is_banned',
    ];

    protected function casts(): array
    {
        return [
            'role'      => 'string',
            'show_name' => 'boolean',
            'is_banned' => 'boolean',
        ];
    }

    /** True for both admin and super_admin. */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /** Shop-only admin (no access to the rest of the admin panel). */
    public function isShopAdmin(): bool
    {
        return $this->role === 'shop_admin';
    }

    /** May manage the shop: dedicated shop admins and super admins. */
    public function canManageShop(): bool
    {
        return in_array($this->role, ['shop_admin', 'super_admin'], true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function problems(): HasMany
    {
        return $this->hasMany(Problem::class);
    }

    public function solutions(): HasMany
    {
        return $this->hasMany(Solution::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function supports(): HasMany
    {
        return $this->hasMany(Support::class);
    }

    public function messengerIdentities(): HasMany
    {
        return $this->hasMany(MessengerIdentity::class);
    }
}
