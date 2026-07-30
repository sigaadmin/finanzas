<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinanceModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $access = $request->user()?->authorizedAccess;
        abort_unless($access?->is_active === true, 403);

        $allowed = match (true) {
            $request->is('finance/u300*') => $access->can_operate_u300,
            $request->is('finance/own-revenue*') => $access->can_operate_own_revenue,
            default => $access->can_operate_ventanilla,
        };

        abort_unless($allowed, 403);

        return $next($request);
    }
}
