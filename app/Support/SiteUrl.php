<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class SiteUrl
{
    public static function route(string $name, mixed $parameters = []): string
    {
        $localizedName = 'en.'.$name;

        return app()->getLocale() === 'en' && Route::has($localizedName)
            ? route($localizedName, $parameters)
            : route($name, $parameters);
    }

    public static function path(string $path): string
    {
        if (app()->getLocale() !== 'en' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $path;
        }

        if ($path === '/en' || str_starts_with($path, '/en/')) {
            return $path;
        }

        return '/en'.($path === '/' ? '' : $path);
    }

    public static function language(string $locale): string
    {
        $path = trim(request()->path(), '/');
        if ($locale === 'en') {
            $path = preg_replace('#^en(?:/|$)#', '', $path) ?? $path;
            $url = '/en'.($path === '' ? '' : '/'.$path);
        } else {
            $path = preg_replace('#^en(?:/|$)#', '', $path) ?? $path;
            $url = $path === '' ? '/' : '/'.$path;
        }

        $query = request()->getQueryString();

        return $url.($query ? '?'.$query : '');
    }
}
