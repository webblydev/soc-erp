<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Crm\CrmServiceProvider;
use App\Modules\Foundation\FoundationServiceProvider;
use App\Modules\Hrm\HrmServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FoundationServiceProvider::class,
    CatalogServiceProvider::class,
    CrmServiceProvider::class,
    HrmServiceProvider::class,
    FortifyServiceProvider::class,
];
