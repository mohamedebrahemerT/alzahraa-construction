<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetSiteLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($request->segment(1) === 'en' ? 'en' : 'ar');

        return $next($request);
    }
}
