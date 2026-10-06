<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\MaterialCategory;
use App\Modules\Catalog\Models\PricingBasis;
use App\Modules\Catalog\Models\ServiceCategory;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\UnitKind;
use App\Modules\Catalog\Models\WorkItemCategory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap catalog services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/catalog'));

        Relation::morphMap([
            'business_line' => BusinessLine::class,
            'service_category' => ServiceCategory::class,
            'pricing_basis' => PricingBasis::class,
            'unit_kind' => UnitKind::class,
            'unit' => Unit::class,
            'work_item_category' => WorkItemCategory::class,
            'material_category' => MaterialCategory::class,
        ]);

        Livewire::addLocation(classNamespace: 'App\\Modules\\Catalog\\Livewire');
    }
}
