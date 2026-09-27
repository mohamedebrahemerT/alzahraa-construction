<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->is_active) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'هذا الحساب غير مخوّل بالدخول.']);
        }

        return $next($request);
    }
}
