<?php

namespace Database\Seeders\Foundation;

use App\Modules\Foundation\Models\Location;
use App\Modules\Foundation\Models\LocationLevel;
use Illuminate\Database\Seeder;

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
