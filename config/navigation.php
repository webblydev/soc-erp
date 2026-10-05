<?php

/*
| Sidebar (desktop) and bottom-nav / More sheet (mobile) registry (docs/00 §7.1, §7.6).
| Items whose route does not exist yet are skipped, so modules add entries as they ship.
| Item: label, route, icon (lucide name), permission (null = any signed-in user), mobile_primary.
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
        ['key' => 'admin', 'label' => 'Admin', 'icon' => 'shield', 'items' => []],
    ],
];
