<?php

namespace App\Actions\Settings;

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EnsureInstitutionalOwner
{
    public const EMAIL = 'administrador.siga@crenfcp.edu.mx';

    public function handle(): User
    {
        AuthorizedAccess::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'role' => UserRole::Owner,
                'is_active' => true,
            ],
        );

        return User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Administrador CREN',
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ],
        );
    }
}
