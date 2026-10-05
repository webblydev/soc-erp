<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\Location;

class SetLocationActive
{
    public function handle(Location $location, bool $active): void
    {
        $location->update(['is_active' => $active]);
    }
}
