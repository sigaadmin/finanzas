<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        AuthorizedAccess::query()->updateOrCreate(
            ['email' => 'administrador.siga@crenfcp.edu.mx'],
            [
                'role' => UserRole::Owner,
                'is_active' => true,
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'administrador.siga@crenfcp.edu.mx'],
            [
                'name' => 'Administrador CREN',
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ],
        );
    }
}
