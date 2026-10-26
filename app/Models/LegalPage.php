<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Testi legali (condizioni di vendita, termini, privacy...) scritti dal
 * pannello e mostrati dal frontend. Quelli con "requires_acceptance" vanno
 * accettati prima di mettere in opzione o confermare una pratica.
 */
class LegalPage extends Model
{
    use HasUniqueSlug;

    protected string $slugSource = 'title';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'requires_acceptance',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_acceptance' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
