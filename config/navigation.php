<?php

/*
| Sidebar (desktop) and bottom-nav / More sheet (mobile) registry (docs/00 §7.1, §7.6).
| Items whose route does not exist yet are skipped, so modules add entries as they ship.
| Item: label, route, icon (lucide name), permission (null = any signed-in user), mobile_primary.
| An item with `children` (label, icon, children: items) is a tree node in the sidebar.
*/

return [
    'groups' => [
        ['key' => 'crm', 'label' => 'CRM', 'icon' => 'handshake', 'items' => []],
        ['key' => 'projects', 'label' => 'Projects', 'icon' => 'folder-kanban', 'items' => []],
        ['key' => 'estimation', 'label' => 'Estimation & Site', 'icon' => 'ruler', 'items' => []],
        ['key' => 'sales', 'label' => 'Sales', 'icon' => 'receipt', 'items' => []],
        ['key' => 'purchases', 'label' => 'Purchases', 'icon' => 'shopping-cart', 'items' => []],
        ['key' => 'accounting', 'label' => 'Accounting', 'icon' => 'landmark', 'items' => []],
        ['key' => 'hrm', 'label' => 'HRM', 'icon' => 'id-card', 'items' => []],
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-column', 'items' => []],
        ['key' => 'admin', 'label' => 'Admin', 'icon' => 'shield', 'items' => [
            ['label' => 'User management', 'icon' => 'users', 'children' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'users', 'permission' => 'admin.users.view'],
                ['label' => 'Roles', 'route' => 'admin.roles.index', 'icon' => 'shield-check', 'permission' => 'admin.roles.view'],
                ['label' => 'Login history', 'route' => 'admin.login-history.index', 'icon' => 'log-in', 'permission' => 'admin.login_history.view'],
            ]],
            ['label' => 'Master data', 'icon' => 'database', 'children' => [
                ['label' => 'Lookups', 'route' => 'admin.master-data.index', 'icon' => 'list', 'permission' => 'admin.master_data.view'],
                ['label' => 'Branches', 'route' => 'admin.branches.index', 'icon' => 'building-2', 'permission' => 'admin.branches.view'],
                ['label' => 'Locations', 'route' => 'admin.locations.index', 'icon' => 'map-pin', 'permission' => 'admin.locations.view'],
            ]],
            ['label' => 'System', 'icon' => 'settings', 'children' => [
                ['label' => 'Company', 'route' => 'admin.company.edit', 'icon' => 'building', 'permission' => 'admin.company.view'],
                ['label' => 'Settings', 'route' => 'admin.settings.edit', 'icon' => 'settings', 'permission' => 'admin.settings.view'],
                ['label' => 'Number sequences', 'route' => 'admin.sequences.index', 'icon' => 'hash', 'permission' => 'admin.sequences.view'],
                ['label' => 'Audit log', 'route' => 'admin.audit.index', 'icon' => 'history', 'permission' => 'admin.audit.view'],
            ]],
        ]],
    ],
];
