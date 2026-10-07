<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Crm\CrmServiceProvider;
use App\Modules\Estimation\EstimationServiceProvider;
use App\Modules\Foundation\FoundationServiceProvider;
use App\Modules\Hrm\HrmServiceProvider;
use App\Modules\Projects\ProjectsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FoundationServiceProvider::class,
    CatalogServiceProvider::class,
    CrmServiceProvider::class,
    HrmServiceProvider::class,
    ProjectsServiceProvider::class,
    EstimationServiceProvider::class,
    FortifyServiceProvider::class,
];
