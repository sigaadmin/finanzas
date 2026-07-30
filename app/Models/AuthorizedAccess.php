<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['email', 'role', 'is_active', 'can_operate_ventanilla', 'can_operate_u300', 'can_operate_own_revenue', 'last_used_at'])]
class AuthorizedAccess extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'can_operate_ventanilla' => 'boolean',
            'can_operate_u300' => 'boolean',
            'can_operate_own_revenue' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'email', 'email');
    }
}
