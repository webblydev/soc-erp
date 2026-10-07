<?php

/*
| Projects permissions (docs/04 §2, spec §4.1) and default role grants. view_own / view_project /
| view_all are data scopes; the gates projects.projects.view and projects.tasks.view mean "any".
| master_data covers the Projects lookups edited on the Master Data screen (spec P23).
*/

$lookup = ['view', 'create', 'update', 'deactivate'];
$ownTasks = ['projects.tasks.view_own', 'projects.tasks.create', 'projects.tasks.update', 'projects.tasks.complete'];

return [
    'permissions' => [
        'projects' => [
            'projects' => ['view_own', 'view_all', 'create', 'update', 'delete', 'change_status', 'close', 'reopen', 'export'],
            'contracts' => ['view', 'manage'],
            'team' => ['manage'],
            'tasks' => ['view_own', 'view_project', 'view_all', 'create', 'update', 'delete', 'assign', 'complete', 'archive'],
            'task_templates' => ['manage'],
            'approvals' => ['view', 'manage'],
            'financials' => ['view'],
            'master_data' => $lookup,
        ],
    ],
    'grants' => [
        'management' => [
            'projects.projects.view_all', 'projects.projects.create', 'projects.projects.update', 'projects.projects.delete',
            'projects.projects.change_status', 'projects.projects.close', 'projects.projects.reopen', 'projects.projects.export',
            'projects.contracts.*', 'projects.team.*',
            'projects.tasks.view_all', 'projects.tasks.create', 'projects.tasks.update', 'projects.tasks.delete',
            'projects.tasks.assign', 'projects.tasks.complete', 'projects.tasks.archive',
            'projects.task_templates.*', 'projects.approvals.*', 'projects.financials.*', 'projects.master_data.*',
        ],
        'project_manager' => [
            'projects.projects.view_own', 'projects.projects.create', 'projects.projects.update', 'projects.projects.change_status',
            'projects.projects.close', 'projects.projects.export',
            'projects.contracts.*', 'projects.team.manage',
            'projects.tasks.view_project', 'projects.tasks.create', 'projects.tasks.update', 'projects.tasks.delete',
            'projects.tasks.assign', 'projects.tasks.complete', 'projects.tasks.archive',
            'projects.task_templates.manage', 'projects.approvals.*', 'projects.financials.view', 'projects.master_data.view',
        ],
        'engineer' => ['projects.projects.view_own', ...$ownTasks, 'projects.approvals.view', 'projects.approvals.manage'],
        'sales_manager' => ['projects.projects.view_own', ...$ownTasks, 'projects.approvals.view'],
        'sales_executive' => ['projects.projects.view_own', ...$ownTasks, 'projects.approvals.view'],
        'accountant' => ['projects.projects.view_all', 'projects.contracts.view', ...$ownTasks, 'projects.approvals.view', 'projects.financials.view'],
        'finance_manager' => ['projects.projects.view_all', 'projects.contracts.view', ...$ownTasks, 'projects.approvals.view', 'projects.financials.view'],
        'hr_admin' => $ownTasks,
    ],
];
