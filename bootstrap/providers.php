<?php

use App\Modules\Foundation\FoundationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FoundationServiceProvider::class,
    FortifyServiceProvider::class,
];
