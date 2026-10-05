<?php

namespace App\Support\Lookups;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared behaviour for [LOOKUP] tables (docs/00 §4.2).
 */
trait IsLookup
{
    public function initializeIsLookup(): void
    {
        $this->mergeCasts([
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ]);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
