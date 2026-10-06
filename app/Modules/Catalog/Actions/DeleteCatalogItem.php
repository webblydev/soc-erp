<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Material;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a catalog item from the list screens' bulk delete. A service quoted on any lead
 * (deleted leads included) is refused so lead screens keep its name; deactivate it instead.
 */
class DeleteCatalogItem
{
    /**
     * @throws ValidationException
     */
    public function handle(Service|Material|WorkItem $item): void
    {
        if ($item instanceof Service && DB::table('lead_services')->where('service_id', $item->id)->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages(['service' => __('Services used on leads cannot be deleted. Deactivate them instead.')]);
        }

        $item->delete();
    }
}
