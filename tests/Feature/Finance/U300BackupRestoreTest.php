<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\U300\U300BackupArchive;
use App\Models\Finance\U300\U300BackupOperation;
use App\Models\User;

function u300BackupUser(UserRole $role): User
{
    $user = User::factory()->create([
        'email' => fake()->unique()->userName().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::create([
        'email' => $user->email,
        'role' => $role,
        'is_active' => true,
    ]);

    return $user;
}

test('only authorized finance roles may manage U300 backups', function () {
    expect(u300BackupUser(UserRole::FinanceAssistant)->canManageU300Backups())->toBeFalse()
        ->and(u300BackupUser(UserRole::FinanceAuditor)->canManageU300Backups())->toBeFalse()
        ->and(u300BackupUser(UserRole::FinanceManager)->canManageU300Backups())->toBeTrue()
        ->and(u300BackupUser(UserRole::Admin)->canManageU300Backups())->toBeTrue();
});

test('backup archives and operations preserve their audit metadata', function () {
    $user = u300BackupUser(UserRole::FinanceManager);
    $archive = U300BackupArchive::query()->create([
        'fiscal_year' => 2026,
        'kind' => 'manual',
        'disk' => 'local',
        'path' => 'u300/backups/backup.zip',
        'original_filename' => 'u300-2026.zip',
        'size_bytes' => 128,
        'sha256' => str_repeat('a', 64),
        'manifest' => ['format_version' => 1],
        'created_by' => $user->id,
    ]);

    $operation = U300BackupOperation::query()->create([
        'u300_backup_archive_id' => $archive->id,
        'fiscal_year' => 2026,
        'type' => 'generated',
        'status' => 'succeeded',
        'performed_by' => $user->id,
        'details' => ['programs' => 1],
    ]);

    expect($archive->manifest)->toBe(['format_version' => 1])
        ->and($operation->details)->toBe(['programs' => 1]);
});
