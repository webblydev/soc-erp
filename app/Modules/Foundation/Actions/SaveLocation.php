<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Creates or edits a location. The level follows from the parent, and full_path is rebuilt
 * for the location and all its descendants whenever its name or parent changes (FD-BR-11).
 */
class SaveLocation
{
    /**
     * @param  array{name?: mixed, name_bn?: mixed, parent_id?: mixed}  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?Location $location = null): Location
    {
        /** @var array{name: string, name_bn?: string|null, parent_id?: int|null} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:locations,id'],
        ])->validate();

        $location ??= new Location;
        $parentId = array_key_exists('parent_id', $input) || ! $location->exists
            ? ($data['parent_id'] ?? null)
            : $location->parent_id;
        $parent = $parentId !== null ? Location::query()->with('level')->findOrFail($parentId) : null;

        $level = $this->levelBelow($parent);
        $this->ensureValidParent($location, $parent, $level);
        $this->ensureUniqueName($location, $parentId, $data['name']);

        return DB::transaction(function () use ($location, $data, $input, $parentId, $parent, $level): Location {
            $pathChanged = ! $location->exists || $location->name !== $data['name'] || $location->parent_id !== $parentId;

            $location->fill([
                'name' => $data['name'],
                'name_bn' => array_key_exists('name_bn', $input)
                    ? (($data['name_bn'] ?? null) === '' ? null : ($data['name_bn'] ?? null))
                    : $location->name_bn,
                'parent_id' => $parentId,
                'location_level_id' => $level->id,
                'full_path' => $this->pathFor($parent, $data['name']),
            ])->save();

            if ($pathChanged) {
                $this->rebuildDescendants($location);
            }

            return $location->load('level');
        });
    }

    private function levelBelow(?Location $parent): LocationLevel
    {
        $index = 0;

        if ($parent !== null) {
            $parentIndex = array_search($parent->level->code, LocationLevel::ORDER, true);

            if ($parentIndex === false) {
                throw new LogicException("Unknown location level [{$parent->level->code}].");
            }

            $index = $parentIndex + 1;
        }

        $code = LocationLevel::ORDER[$index] ?? null;

        if ($code === null) {
            throw ValidationException::withMessages(['parent_id' => __('Areas cannot have child locations.')]);
        }

        return LocationLevel::query()->where('code', $code)->firstOrFail();
    }

    private function ensureValidParent(Location $location, ?Location $parent, LocationLevel $level): void
    {
        if (! $location->exists) {
            return;
        }

        $ancestor = $parent;

        while ($ancestor !== null) {
            if ($ancestor->is($location)) {
                throw ValidationException::withMessages(['parent_id' => __('A location cannot be moved under itself.')]);
            }

            $ancestor = $ancestor->parent;
        }

        if ($location->location_level_id !== $level->id) {
            throw ValidationException::withMessages(['parent_id' => __('The new parent must be on the same level as the current one.')]);
        }
    }

    private function ensureUniqueName(Location $location, ?int $parentId, string $name): void
    {
        $taken = Location::query()
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->when($location->exists, fn ($query) => $query->whereKeyNot($location->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('A location with this name already exists here.')]);
        }
    }

    private function pathFor(?Location $parent, string $name): string
    {
        return $parent === null ? $name : $parent->full_path.Location::PATH_SEPARATOR.$name;
    }

    private function rebuildDescendants(Location $location): void
    {
        foreach (Location::query()->where('parent_id', $location->id)->get() as $child) {
            $child->update(['full_path' => $this->pathFor($location, $child->name)]);
            $this->rebuildDescendants($child);
        }
    }
}
