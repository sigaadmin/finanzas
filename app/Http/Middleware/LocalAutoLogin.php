<?php

namespace App\Http\Middleware;

use App\Models\AuthorizedAccess;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LocalAutoLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('finance.local_auto_login.enabled') || app()->isProduction() || Auth::check()) {
            return $next($request);
        }

        if (! in_array(app()->environment(), config('finance.local_auto_login.environments', ['local']), true)) {
            return $next($request);
        }

        $email = mb_strtolower(trim((string) config('finance.local_auto_login.email')));

        if ($email === '') {
            return $next($request);
        }

        $access = AuthorizedAccess::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (! $access) {
            return $next($request);
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador CREN',
                'password' => Hash::make(Str::random(48)),
            ],
        );

        if (! $user->email_verified_at) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        Auth::login($user);

        $access->forceFill([
            'last_used_at' => now(),
        ])->save();

        return $next($request);
    }
}
