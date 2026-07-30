<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAuthorizedAccessRequest;
use App\Http\Requests\Settings\UpdateAuthorizedAccessRequest;
use App\Models\AuthorizedAccess;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AuthorizedAccessController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/users', [
            'users' => AuthorizedAccess::query()
                ->orderBy('email')
                ->get()
                ->map(fn (AuthorizedAccess $access): array => [
                    'id' => $access->id,
                    'email' => $access->email,
                    'role' => $access->role->value,
                    'is_active' => $access->is_active,
                    'can_operate_ventanilla' => $access->can_operate_ventanilla,
                    'can_operate_u300' => $access->can_operate_u300,
                    'can_operate_own_revenue' => $access->can_operate_own_revenue,
                    'last_used_at' => $access->last_used_at?->toDateTimeString(),
                ]),
        ]);
    }

    public function store(StoreAuthorizedAccessRequest $request): RedirectResponse
    {
        AuthorizedAccess::create($this->attributes($request->validated()));

        Inertia::flash('success', 'Usuario autorizado correctamente.');

        return to_route('authorized-accesses.index');
    }

    public function update(UpdateAuthorizedAccessRequest $request, AuthorizedAccess $user): RedirectResponse
    {
        $user->update($this->attributes($request->validated()));

        Inertia::flash('success', 'Permisos actualizados correctamente.');

        return to_route('authorized-accesses.index');
    }

    /** @param array<string, mixed> $validated */
    private function attributes(array $validated): array
    {
        $isAdministrator = $validated['role'] === UserRole::Admin->value;

        return [
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'can_operate_ventanilla' => $isAdministrator || ($validated['can_operate_ventanilla'] ?? false),
            'can_operate_u300' => $isAdministrator || ($validated['can_operate_u300'] ?? false),
            'can_operate_own_revenue' => $isAdministrator || ($validated['can_operate_own_revenue'] ?? false),
        ];
    }
}
