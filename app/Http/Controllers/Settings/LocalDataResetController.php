<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\ResetLocalData;
use App\Enums\Settings\LocalDataResetScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ResetLocalDataRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LocalDataResetController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isOwner() === true, 403);

        return Inertia::render('settings/local-data', [
            'scopes' => collect(LocalDataResetScope::cases())
                ->map(fn (LocalDataResetScope $scope): array => [
                    'value' => $scope->value,
                    'label' => $scope->label(),
                    'description' => $scope->description(),
                    'preserves' => $scope->preserves(),
                    'confirmation_phrase' => $scope->confirmationPhrase(),
                    'is_global' => $scope->isGlobal(),
                ])
                ->values(),
        ]);
    }

    public function store(
        ResetLocalDataRequest $request,
        string $scope,
        ResetLocalData $reset,
    ): RedirectResponse {
        $resetScope = $request->scope();
        $result = $reset->handle($resetScope);

        if ($resetScope === LocalDataResetScope::All) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('home');
        }

        $recordSummary = $result->deletedRecords === 1
            ? '1 registro eliminado'
            : "{$result->deletedRecords} registros eliminados";

        Inertia::flash('success', "{$resetScope->label()} se reinició correctamente: {$recordSummary}.");

        if ($result->fileWarnings !== []) {
            Inertia::flash('warning', implode(' ', $result->fileWarnings));
        }

        return to_route('local-data.index');
    }
}
