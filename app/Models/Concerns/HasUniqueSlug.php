<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Genera automaticamente uno slug univoco dal campo indicato in $slugSource
 * (default "name") quando lo slug non viene valorizzato.
 */
trait HasUniqueSlug
{
    protected static function bootHasUniqueSlug(): void
    {
        static::saving(function ($model) {
            if (filled($model->slug)) {
                return;
            }

            $base = Str::slug($model->{$model->slugSource ?? 'name'}) ?: Str::random(8);
            $slug = $base;
            $i = 2;

            while (static::withoutGlobalScopes()->where('slug', $slug)->whereKeyNot($model->getKey())->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }

            $model->slug = $slug;
        });
    }
}
