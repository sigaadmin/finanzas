<?php

use App\Enums\Settings\LocalDataResetScope;
use App\Http\Controllers\Settings\LocalDataResetController;
use App\Http\Middleware\EnsureLocalDataResetIsAvailable;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/appearance');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::middleware(EnsureLocalDataResetIsAvailable::class)->group(function () {
        Route::get('settings/local-data', [LocalDataResetController::class, 'index'])
            ->name('local-data.index');

        Route::post('settings/local-data/{scope}', [LocalDataResetController::class, 'store'])
            ->whereIn('scope', array_column(LocalDataResetScope::cases(), 'value'))
            ->name('local-data.reset');
    });
});
