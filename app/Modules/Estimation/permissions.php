<?php

/*
| Estimation & Site permissions (docs/05 §2, spec §4.1) and default role grants. The data scope
| comes from the project (spec E4), so there are no view_own / view_all variants.
| master_data covers the Estimation lookups edited on the Master Data screen (spec E23).
*/

$lookup = ['view', 'create', 'update', 'deactivate'];

return [
    'permissions' => [
        'estimation' => [
            'estimates' => ['view', 'create', 'update', 'submit', 'approve', 'revise', 'delete', 'print', 'export'],
            'budget' => ['view', 'manage'],
            'master_data' => $lookup,
        ],
        'site' => [
            'mb' => ['view', 'create', 'update', 'verify', 'edit_rate', 'delete'],
            'inspections' => ['view', 'create', 'update', 'close_finding', 'delete', 'print'],
        ],
    ],
    'grants' => [
        'management' => ['estimation.*', 'site.*'],
        'project_manager' => [
            'estimation.estimates.*', 'estimation.budget.*', 'estimation.master_data.view',
            'site.mb.*', 'site.inspections.*',
        ],
        'engineer' => [
            'estimation.estimates.view', 'estimation.estimates.create', 'estimation.estimates.update', 'estimation.estimates.submit',
            'estimation.estimates.revise', 'estimation.estimates.print', 'estimation.budget.view',
            'site.mb.view', 'site.mb.create', 'site.mb.update', 'site.mb.delete',
            'site.inspections.view', 'site.inspections.create', 'site.inspections.update', 'site.inspections.close_finding', 'site.inspections.print',
        ],
        'accountant' => ['estimation.estimates.view', 'estimation.estimates.print', 'estimation.estimates.export', 'estimation.budget.view', 'site.mb.view', 'site.inspections.view'],
        'finance_manager' => ['estimation.estimates.view', 'estimation.estimates.print', 'estimation.estimates.export', 'estimation.budget.view', 'site.mb.view', 'site.inspections.view'],
        'sales_manager' => ['estimation.estimates.view', 'estimation.estimates.print'],
        'sales_executive' => ['estimation.estimates.view', 'estimation.estimates.print'],
    ],
];
