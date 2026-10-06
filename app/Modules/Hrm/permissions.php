<?php

/*
| HRM permissions (docs/09 §2, spec §4.1) and default role grants. hrm.masters replaces docs/09's
| single masters.manage because Master Data checks view / create / update / deactivate (spec H10).
| Payroll permissions (hrm.salary.*) come with phase 9b (spec H18).
*/

return [
    'permissions' => [
        'hrm' => [
            'employees' => ['view_basic', 'view_full', 'create', 'update', 'deactivate', 'export', 'view_salary', 'update_salary'],
            'documents' => ['view', 'manage'],
            'history' => ['manage'],
            'masters' => ['view', 'create', 'update', 'deactivate'],
        ],
    ],
    'grants' => [
        'management' => ['hrm.employees.*', 'hrm.documents.*', 'hrm.history.*', 'hrm.masters.*'],
        'hr_admin' => ['hrm.employees.*', 'hrm.documents.*', 'hrm.history.*', 'hrm.masters.*'],
        'finance_manager' => ['hrm.employees.view_basic', 'hrm.employees.view_salary'],
        'accountant' => ['hrm.employees.view_basic', 'hrm.employees.view_salary'],
        'project_manager' => ['hrm.employees.view_basic'],
        'engineer' => ['hrm.employees.view_basic'],
        'sales_manager' => ['hrm.employees.view_basic'],
        'sales_executive' => ['hrm.employees.view_basic'],
        'viewer' => ['hrm.employees.view_basic'],
    ],
];
