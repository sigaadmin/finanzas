<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\ResolveAuthorizedGoogleLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with([
                'hd' => config('auth_access.google.domain'),
                'prompt' => 'select_account',
            ])
            ->redirect();
    }

    public function callback(Request $request, ResolveAuthorizedGoogleLogin $resolveLogin): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $user = $resolveLogin->handle($googleUser->getEmail(), $googleUser->getName(), $googleUser->getRaw());
        } catch (\Throwable $exception) {
            report($exception);

            return to_route('login')->with('status', 'No fue posible completar el acceso con Google. Intenta nuevamente.');
        }

        if (! $user) {
            return to_route('login')->with('status', 'Tu cuenta no tiene acceso autorizado al Portal Financiero.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
