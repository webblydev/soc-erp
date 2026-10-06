<?php

namespace App\Modules\Crm\Concerns;

use App\Modules\Foundation\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Location filters include every place under the chosen one (docs/03 §5.1 "location (tree)").
 */
trait FiltersByLocation
{
    /**
     * @param  Builder<covariant Model>  $query
     */
    protected function whereInLocation(Builder $query, string $column, int $locationId): void
    {
        $path = Location::query()->whereKey($locationId)->value('full_path');

        if ($path === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $like = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $path.Location::PATH_SEPARATOR).'%';

        $query->whereIn($column, Location::query()->select('id')
            ->where(fn ($query) => $query->where('full_path', $path)->orWhereRaw("full_path LIKE ? ESCAPE '!'", [$like])));
    }
}
