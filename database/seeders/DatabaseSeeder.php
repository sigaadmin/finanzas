<?php

namespace Database\Seeders;

use App\Actions\Settings\EnsureInstitutionalOwner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(EnsureInstitutionalOwner $ensureOwner): void
    {
        $ensureOwner->handle();
    }
}
