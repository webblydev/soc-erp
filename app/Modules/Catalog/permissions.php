<?php

/*
| Catalog permissions (docs/02 §2) and default role grants.
| master_data covers the category lookups edited on the Master Data screen.
*/

$lookup = ['view', 'create', 'update', 'deactivate'];
$viewAll = ['catalog.*.view'];

return [
    'permissions' => [
        'catalog' => [
            'business_lines' => $lookup,
            'services' => ['view', 'create', 'update', 'delete', 'export'],
            'units' => $lookup,
            'work_items' => ['view', 'create', 'update', 'delete', 'import', 'export'],
            'materials' => ['view', 'create', 'update', 'delete', 'import', 'export'],
            'master_data' => $lookup,
        ],
    ],
    'grants' => [
        'management' => ['catalog.*'],
        'project_manager' => [...$viewAll, 'catalog.work_items.*', 'catalog.materials.*'],
        'accountant' => $viewAll,
        'finance_manager' => $viewAll,
        'sales_manager' => $viewAll,
        'sales_executive' => $viewAll,
        'engineer' => $viewAll,
    ],
];
