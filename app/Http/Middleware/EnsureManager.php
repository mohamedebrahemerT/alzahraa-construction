<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManager
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === 'manager', 403, 'هذه العملية متاحة للمدير فقط.');

        return $next($request);
    }
}
