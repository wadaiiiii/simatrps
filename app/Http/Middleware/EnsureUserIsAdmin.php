<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user && (bool) $user->is_active && $user->role === 'admin',
            403,
            'Halaman ini hanya dapat diakses Admin SiMatRPS.'
        );

        return $next($request);
    }
}
