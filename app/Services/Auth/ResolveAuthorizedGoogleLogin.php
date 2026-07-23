<?php

namespace App\Services\Auth;

use App\Models\AuthorizedAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResolveAuthorizedGoogleLogin
{
    /**
     * Resolve an active institutional authorization to an application user.
     */
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(?string $email, ?string $name, array $attributes): ?User
    {
        $email = mb_strtolower(trim((string) $email));
        $domain = mb_strtolower(trim((string) config('auth_access.google.domain')));
        $hostedDomain = mb_strtolower(trim((string) ($attributes['hd'] ?? '')));
        $isEmailVerified = ($attributes['verified_email'] ?? false) === true;

        if ($email === '' || $domain === '' || ! $isEmailVerified || $hostedDomain !== $domain || ! str_ends_with($email, '@'.$domain)) {
            return null;
        }

        return DB::transaction(function () use ($email, $name): ?User {
            $access = AuthorizedAccess::query()
                ->where('email', $email)
                ->where('is_active', true)
                ->first();

            if (! $access) {
                return null;
            }

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => filled($name) ? $name : $email,
                    'password' => Hash::make(Str::random(48)),
                    'email_verified_at' => now(),
                ],
            );

            $updates = array_filter([
                'name' => filled($name) ? $name : null,
                'email_verified_at' => $user->email_verified_at ? null : now(),
            ]);

            if ($updates !== []) {
                $user->forceFill($updates)->save();
            }

            $access->forceFill(['last_used_at' => now()])->save();

            return $user;
        });
    }
}
