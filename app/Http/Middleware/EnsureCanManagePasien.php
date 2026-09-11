<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManagePasien
{
    /**
     * Allow Superadmin (all patients) or Admin / Puskesmas (scoped) to manage patients.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->canManagePatients()) {
            abort(403);
        }

        return $next($request);
    }
}
