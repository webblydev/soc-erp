<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use Illuminate\Database\Seeder;

/**
 * Bangladesh divisions, districts and thanas (docs/01 §4) plus the v1 areas (legacy seed spec L4).
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LocationLevelSeeder::class);

        $levels = LocationLevel::query()->pluck('id', 'code');

        /** @var array<string, array<string, list<string>>> $divisions */
        $divisions = require database_path('seeders/Foundation/data/locations.php');

        foreach ($divisions as $divisionName => $districts) {
            $division = $this->place(null, $divisionName, (int) $levels[LocationLevel::DIVISION]);

            foreach ($districts as $districtName => $thanas) {
                $district = $this->place($division, $districtName, (int) $levels[LocationLevel::DISTRICT]);

                foreach ($thanas as $thanaName) {
                    $this->place($district, $thanaName, (int) $levels[LocationLevel::THANA]);
                }
            }
        }

        $this->placeLegacyAreas($levels->all());
    }

    /**
     * The v1 areas (data/legacy_areas.php): each path is walked from the division down, and
     * missing thana / area nodes are created at the level of their depth.
     *
     * @param  array<string, int>  $levels
     */
    private function placeLegacyAreas(array $levels): void
    {
        /** @var array<int, list<string>|null> $areas */
        $areas = require database_path('seeders/Foundation/data/legacy_areas.php');

        foreach (array_filter($areas) as $path) {
            $parent = null;

            foreach ($path as $depth => $name) {
                $parent = $this->place($parent, $name, (int) $levels[LocationLevel::ORDER[$depth]]);
            }
        }
    }

    private function place(?Location $parent, string $name, int $levelId): Location
    {
        return Location::withoutEvents(fn (): Location => Location::query()->firstOrCreate(
            ['parent_id' => $parent?->id, 'name' => $name],
            [
                'location_level_id' => $levelId,
                'full_path' => $parent === null ? $name : $parent->full_path.Location::PATH_SEPARATOR.$name,
            ],
        ));
    }
}
