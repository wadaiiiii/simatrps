<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedRoutes = ['security.edit', 'user-password.update', 'logout'];

        if ($request->user()?->must_change_password && ! in_array($request->route()?->getName(), $allowedRoutes, true)) {
            return redirect()->route('security.edit')->with('status', 'Silakan ganti kata sandi sementara sebelum melanjutkan.');
        }

        return $next($request);
    }
}
