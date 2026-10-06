<?php

/*
| Foundation & Administration permissions (docs/01 §2) and default role grants.
| super_admin needs no grants: Gate::before lets it through everything.
*/

$collaborate = ['attachments.upload', 'attachments.delete_own', 'notes.create', 'notes.delete_own'];

return [
    'permissions' => [
        'admin' => [
            'users' => ['view', 'create', 'update', 'deactivate', 'delete', 'reset_password', 'impersonate'],
            'roles' => ['view', 'create', 'update', 'delete'],
            'settings' => ['view', 'update'],
            'company' => ['view', 'update'],
            'branches' => ['view', 'create', 'update', 'deactivate'],
            'master_data' => ['view', 'create', 'update', 'deactivate'],
            'locations' => ['view', 'create', 'update', 'deactivate'],
            'sequences' => ['view', 'update'],
            'audit' => ['view', 'export'],
            'login_history' => ['view'],
        ],
        'attachments' => ['' => ['upload', 'delete_own', 'delete_any']],
        'notes' => ['' => ['create', 'delete_own', 'delete_any']],
    ],
    'grants' => [
        'management' => ['admin.*.view', 'admin.settings.update', 'admin.company.update', 'admin.audit.export', 'attachments.*', 'notes.*'],
        'hr_admin' => [
            'admin.users.view', 'admin.users.create', 'admin.users.update', 'admin.users.deactivate', 'admin.users.reset_password',
            'admin.branches.*', 'admin.locations.*', 'admin.master_data.view', ...$collaborate,
        ],
        'sales_manager' => $collaborate,
        'sales_executive' => $collaborate,
        'project_manager' => $collaborate,
        'engineer' => $collaborate,
        'accountant' => $collaborate,
        'finance_manager' => $collaborate,
        'viewer' => ['*.view', '*.view_all'],
    ],
];
