<?php

/*
| Sidebar (desktop) and bottom-nav / More sheet (mobile) registry (docs/00 §7.1, §7.6).
| Items whose route does not exist yet are skipped, so modules add entries as they ship.
| Item: label, route, params (route parameters, optional), icon (lucide name), permission (null =
| any signed-in user), mobile_primary.
| An item with `children` (label, icon, children: items) is a tree node in the sidebar.
*/

return [
    'groups' => [
        ['key' => 'crm', 'label' => 'CRM', 'icon' => 'handshake', 'items' => []],
        ['key' => 'catalog', 'label' => 'Catalog', 'icon' => 'package', 'items' => [
            ['label' => 'Services', 'route' => 'catalog.services.index', 'icon' => 'clipboard-list', 'permission' => 'catalog.services.view'],
            ['label' => 'Work items', 'route' => 'catalog.work-items.index', 'icon' => 'hammer', 'permission' => 'catalog.work_items.view'],
            ['label' => 'Materials', 'route' => 'catalog.materials.index', 'icon' => 'boxes', 'permission' => 'catalog.materials.view'],
            ['label' => 'Catalog setup', 'icon' => 'database', 'children' => [
                ['label' => 'Business lines', 'route' => 'admin.master-data.show', 'params' => ['table' => 'business_lines'], 'icon' => 'briefcase', 'permission' => 'catalog.business_lines.view'],
                ['label' => 'Units', 'route' => 'admin.master-data.show', 'params' => ['table' => 'units'], 'icon' => 'ruler', 'permission' => 'catalog.units.view'],
                ['label' => 'Unit kinds', 'route' => 'admin.master-data.show', 'params' => ['table' => 'unit_kinds'], 'icon' => 'shapes', 'permission' => 'catalog.master_data.view'],
                ['label' => 'Service categories', 'route' => 'admin.master-data.show', 'params' => ['table' => 'service_categories'], 'icon' => 'tags', 'permission' => 'catalog.master_data.view'],
                ['label' => 'Pricing bases', 'route' => 'admin.master-data.show', 'params' => ['table' => 'pricing_bases'], 'icon' => 'tags', 'permission' => 'catalog.master_data.view'],
                ['label' => 'Work item categories', 'route' => 'admin.master-data.show', 'params' => ['table' => 'work_item_categories'], 'icon' => 'tags', 'permission' => 'catalog.master_data.view'],
                ['label' => 'Material categories', 'route' => 'admin.master-data.show', 'params' => ['table' => 'material_categories'], 'icon' => 'tags', 'permission' => 'catalog.master_data.view'],
            ]],
        ]],
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
                ['label' => 'Branches', 'route' => 'admin.master-data.show', 'params' => ['table' => 'branches'], 'icon' => 'building-2', 'permission' => 'admin.branches.view'],
                ['label' => 'Currencies', 'route' => 'admin.master-data.show', 'params' => ['table' => 'currencies'], 'icon' => 'banknote', 'permission' => 'admin.master_data.view'],
                ['label' => 'Document types', 'route' => 'admin.master-data.show', 'params' => ['table' => 'document_types'], 'icon' => 'file-text', 'permission' => 'admin.master_data.view'],
                ['label' => 'Location levels', 'route' => 'admin.master-data.show', 'params' => ['table' => 'location_levels'], 'icon' => 'layers', 'permission' => 'admin.locations.view'],
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
