<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return HasOne<AuthorizedAccess, $this>
     */
    public function authorizedAccess(): HasOne
    {
        return $this->hasOne(AuthorizedAccess::class, 'email', 'email');
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->authorizedAccess?->is_active === true
            && $this->authorizedAccess->role === $role;
    }

    public function isOwner(): bool
    {
        return $this->hasRole(UserRole::Owner);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    public function isFinanceManager(): bool
    {
        return $this->hasRole(UserRole::FinanceManager);
    }

    public function isFinanceAssistant(): bool
    {
        return $this->hasRole(UserRole::FinanceAssistant);
    }

    public function isFinanceAuditor(): bool
    {
        return $this->hasRole(UserRole::FinanceAuditor);
    }

    public function canOperateFinance(): bool
    {
        return $this->authorizedAccess?->is_active === true
            && in_array($this->authorizedAccess->role, [
                UserRole::Owner,
                UserRole::Admin,
                UserRole::FinanceManager,
                UserRole::FinanceAssistant,
                UserRole::FinanceAuditor,
            ], true);
    }

    public function canManageExpenseClassifications(): bool
    {
        return $this->authorizedAccess?->is_active === true
            && in_array($this->authorizedAccess->role, [
                UserRole::Owner,
                UserRole::Admin,
                UserRole::FinanceManager,
            ], true);
    }

    public function canManageU300Backups(): bool
    {
        return $this->authorizedAccess?->is_active === true
            && in_array($this->authorizedAccess->role, [
                UserRole::Owner,
                UserRole::Admin,
                UserRole::FinanceManager,
            ], true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
