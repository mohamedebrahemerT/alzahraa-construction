<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'translations' => 'array', 'is_published' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function applyLocale(?string $locale = null): static
    {
        $translations = $this->translations[$locale ?? app()->getLocale()] ?? [];
        foreach (['title', 'category', 'description', 'body', 'alt', 'location', 'quantity'] as $attribute) {
            if (filled($translations[$attribute] ?? null)) {
                $this->setAttribute($attribute, $translations[$attribute]);
            }
        }

        return $this;
    }
}
