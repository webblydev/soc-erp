<?php

namespace Database\Seeders\Catalog;

use App\Modules\Catalog\Models\MaterialCategory;
use App\Modules\Catalog\Models\ServiceCategory;
use App\Modules\Catalog\Models\WorkItemCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Service, work item and material categories (docs/02 §3.2, §3.5, §3.7).
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRows(ServiceCategory::class, [
            'DESIGN' => 'Design', 'APPROVAL' => 'Approval & Permits', 'WORKS' => 'Construction & Works',
            'ENGG' => 'Engineering & Supervision', 'SURVEY' => 'Survey & Testing', 'ESTIMATE' => 'Estimating',
            'TRAINING' => 'Training', 'OTHER' => 'Other',
        ]);

        $this->seedRows(WorkItemCategory::class, [
            'EARTHWORK' => 'Earthwork', 'CONCRETE' => 'Concrete', 'REINFORCEMENT' => 'Reinforcement', 'MASONRY' => 'Masonry',
            'PLASTER' => 'Plaster', 'FLOORING' => 'Flooring', 'PAINTING' => 'Painting', 'DOORS_WINDOWS' => 'Doors & Windows',
            'ELECTRICAL' => 'Electrical', 'PLUMBING' => 'Plumbing', 'SANITARY' => 'Sanitary', 'INTERIOR' => 'Interior',
            'STEEL_WORKS' => 'Steel Works', 'MISC' => 'Miscellaneous',
        ]);

        $this->seedRows(MaterialCategory::class, [
            'CEMENT' => 'Cement', 'SAND' => 'Sand', 'BRICK' => 'Brick', 'STONE_AGGREGATE' => 'Stone / Aggregate',
            'ROD_STEEL' => 'Rod / Steel', 'TILES' => 'Tiles', 'PAINT' => 'Paint', 'WOOD' => 'Wood', 'ELECTRICAL' => 'Electrical',
            'PLUMBING' => 'Plumbing', 'SANITARY' => 'Sanitary', 'GLASS_ALUMINIUM' => 'Glass & Aluminium', 'OTHERS' => 'Others',
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, string>  $rows
     */
    private function seedRows(string $model, array $rows): void
    {
        $order = 0;

        foreach ($rows as $code => $name) {
            $model::query()->firstOrCreate(['code' => $code], ['name' => $name, 'sort_order' => ++$order]);
        }
    }
}
