<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\Location;
use Illuminate\Support\Facades\DB;

class SetLocationActive
{
    public function handle(Location $location, bool $active): void
    {
        DB::transaction(fn () => $location->update(['is_active' => $active]));
    }
}
