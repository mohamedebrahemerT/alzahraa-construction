<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function allValues(): array
    {
        return Cache::remember('site.settings', 60, fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        $value = static::allValues()[$key] ?? $default;
        if ($key !== 'general' || ! is_array($value) || app()->getLocale() !== 'en') {
            return $value;
        }

        $translation = $value['translations']['en'] ?? [];
        foreach (['company_name', 'tagline', 'address', 'footer_note', 'header_cta_label'] as $attribute) {
            if (filled($translation[$attribute] ?? null)) {
                $value[$attribute] = $translation[$attribute];
            }
        }
        foreach (['nav', 'footer_nav'] as $menu) {
            if (isset($value[$menu]) && is_array($value[$menu])) {
                foreach ($value[$menu] as $index => $link) {
                    foreach (['label', 'url'] as $attribute) {
                        if (filled($translation[$menu][$index][$attribute] ?? null)) {
                            $value[$menu][$index][$attribute] = $translation[$menu][$index][$attribute];
                        }
                    }
                }
            }
        }

        return $value;
    }

    public static function refreshCache(): void
    {
        Cache::forget('site.settings');
    }
}
