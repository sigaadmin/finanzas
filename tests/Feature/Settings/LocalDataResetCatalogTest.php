<?php

use App\Data\Settings\LocalDataResetResult;
use App\Enums\Settings\LocalDataResetScope;
use App\Services\Settings\LocalDataResetCatalog;
use Illuminate\Support\Facades\Schema;

test('reset scopes expose their exact confirmation phrases', function () {
    expect(LocalDataResetScope::Ventanilla->confirmationPhrase())->toBe('BORRAR VENTANILLA')
        ->and(LocalDataResetScope::U300->confirmationPhrase())->toBe('BORRAR U300')
        ->and(LocalDataResetScope::OwnRevenue->confirmationPhrase())->toBe('BORRAR INGRESOS PROPIOS')
        ->and(LocalDataResetScope::All->confirmationPhrase())->toBe('REINICIAR TODO');
});

test('every application table has an explicit reset decision', function () {
    $schemaTables = collect(Schema::getTableListing())
        ->map(fn (string $table): string => str($table)->afterLast('.')->toString())
        ->reject(fn (string $table): bool => $table === 'migrations' || str_starts_with($table, 'sqlite_'))
        ->sort()
        ->values()
        ->all();

    $catalogTables = app(LocalDataResetCatalog::class)
        ->applicationTables()
        ->sort()
        ->values()
        ->all();

    expect($catalogTables)->toBe($schemaTables);
});

test('module file roots are explicit and isolated', function () {
    $catalog = app(LocalDataResetCatalog::class);

    expect($catalog->fileRootsFor(LocalDataResetScope::Ventanilla))->toBe([])
        ->and($catalog->fileRootsFor(LocalDataResetScope::U300))->toBe([
            ['disk' => 'local', 'path' => 'u300/imports'],
            ['disk' => 'public', 'path' => 'u300/technical-sheets/reference-photos'],
        ])
        ->and($catalog->fileRootsFor(LocalDataResetScope::OwnRevenue))->toBe([
            ['disk' => 'local', 'path' => 'own-revenue/imports'],
            ['disk' => 'local', 'path' => 'own-revenue/exports'],
            ['disk' => 'local', 'path' => 'finance/own-revenue'],
        ]);
});

test('reset results carry counts and file warnings without mutation', function () {
    $result = new LocalDataResetResult(
        scope: LocalDataResetScope::U300,
        deletedRecords: 14,
        fileWarnings: ['No fue posible limpiar una carpeta de U300.'],
    );

    expect($result->scope)->toBe(LocalDataResetScope::U300)
        ->and($result->deletedRecords)->toBe(14)
        ->and($result->fileWarnings)->toBe(['No fue posible limpiar una carpeta de U300.']);
});
