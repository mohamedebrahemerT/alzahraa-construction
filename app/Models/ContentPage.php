<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sections' => 'array', 'translations' => 'array'];
    }

    public function applyLocale(?string $locale = null): static
    {
        $locale ??= app()->getLocale();
        $translations = $this->translations[$locale] ?? [];
        foreach (['title', 'meta_title', 'meta_description'] as $attribute) {
            if (filled($translations[$attribute] ?? null)) {
                $this->setAttribute($attribute, $translations[$attribute]);
            }
        }

        if ($locale !== 'ar') {
            $sections = $this->sections ?? [];
            foreach ($sections as $index => &$section) {
                $localized = $section['translations'][$locale] ?? [];
                foreach (['title', 'text', 'alt', 'primary_label', 'secondary_label', 'link_label'] as $attribute) {
                    if (filled($localized[$attribute] ?? null)) {
                        $section[$attribute] = $localized[$attribute];
                    }
                }
                if (isset($section['items']) && is_array($section['items'])) {
                    foreach ($section['items'] as &$item) {
                        $itemTranslation = $item['translations'][$locale] ?? [];
                        foreach (['value', 'label', 'title', 'text'] as $attribute) {
                            if (filled($itemTranslation[$attribute] ?? null)) {
                                $item[$attribute] = $itemTranslation[$attribute];
                            }
                        }
                    }
                    unset($item);
                }
            }
            unset($section);
            $this->setAttribute('sections', $sections);
        }

        return $this;
    }
}
