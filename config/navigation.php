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
        ['key' => 'crm', 'label' => 'CRM', 'icon' => 'handshake', 'items' => [
            ['label' => 'Leads', 'route' => 'crm.leads.index', 'icon' => 'target', 'permission' => 'crm.leads.view', 'mobile_primary' => true],
            ['label' => 'My activities', 'route' => 'crm.activities.index', 'icon' => 'calendar-check', 'permission' => 'crm.activities.view', 'mobile_primary' => true],
            ['label' => 'Customers', 'route' => 'crm.customers.index', 'icon' => 'contact', 'permission' => 'crm.customers.view', 'mobile_primary' => true],
            ['label' => 'Sales teams', 'route' => 'crm.teams.index', 'icon' => 'users-round', 'permission' => 'crm.teams.view'],
            ['label' => 'Master data', 'icon' => 'database', 'children' => [
                ['label' => 'Lead sources', 'route' => 'admin.master-data.show', 'params' => ['table' => 'lead_sources'], 'icon' => 'tags', 'permission' => 'crm.master_data.view'],
                ['label' => 'Lead statuses', 'route' => 'admin.master-data.show', 'params' => ['table' => 'lead_statuses'], 'icon' => 'tags', 'permission' => 'crm.master_data.view'],
                ['label' => 'Lead priorities', 'route' => 'admin.master-data.show', 'params' => ['table' => 'lead_priorities'], 'icon' => 'tags', 'permission' => 'crm.master_data.view'],
                ['label' => 'Lead levels', 'route' => 'admin.master-data.show', 'params' => ['table' => 'lead_levels'], 'icon' => 'tags', 'permission' => 'crm.master_data.view'],
                ['label' => 'Lost reasons', 'route' => 'admin.master-data.show', 'params' => ['table' => 'lost_reasons'], 'icon' => 'tags', 'permission' => 'crm.master_data.view'],
                ['label' => 'Activity types', 'route' => 'admin.master-data.show', 'params' => ['table' => 'activity_types'], 'icon' => 'tags', 'permission' => 'crm.master_data.view'],
                ['label' => 'Activity outcomes', 'route' => 'admin.master-data.show', 'params' => ['table' => 'activity_outcomes'], 'icon' => 'tags', 'permission' => 'crm.master_data.view'],
                ['label' => 'Customer types', 'route' => 'admin.master-data.show', 'params' => ['table' => 'customer_types'], 'icon' => 'tags', 'permission' => 'crm.master_data.view'],
                ['label' => 'Customer statuses', 'route' => 'admin.master-data.show', 'params' => ['table' => 'customer_statuses'], 'icon' => 'tags', 'permission' => 'crm.master_data.view'],
                ['label' => 'Payment terms', 'route' => 'admin.master-data.show', 'params' => ['table' => 'payment_terms'], 'icon' => 'tags', 'permission' => 'crm.master_data.view'],
            ]],
        ]],
        ['key' => 'catalog', 'label' => 'Catalog', 'icon' => 'package', 'items' => [
            ['label' => 'Services', 'route' => 'catalog.services.index', 'icon' => 'clipboard-list', 'permission' => 'catalog.services.view'],
            ['label' => 'Work items', 'route' => 'catalog.work-items.index', 'icon' => 'hammer', 'permission' => 'catalog.work_items.view'],
            ['label' => 'Materials', 'route' => 'catalog.materials.index', 'icon' => 'boxes', 'permission' => 'catalog.materials.view'],
            ['label' => 'Master data', 'icon' => 'database', 'children' => [
                ['label' => 'Business lines', 'route' => 'admin.master-data.show', 'params' => ['table' => 'business_lines'], 'icon' => 'briefcase', 'permission' => 'catalog.business_lines.view'],
                ['label' => 'Units', 'route' => 'admin.master-data.show', 'params' => ['table' => 'units'], 'icon' => 'ruler', 'permission' => 'catalog.units.view'],
                ['label' => 'Unit kinds', 'route' => 'admin.master-data.show', 'params' => ['table' => 'unit_kinds'], 'icon' => 'shapes', 'permission' => 'catalog.master_data.view'],
                ['label' => 'Service categories', 'route' => 'admin.master-data.show', 'params' => ['table' => 'service_categories'], 'icon' => 'tags', 'permission' => 'catalog.master_data.view'],
                ['label' => 'Pricing bases', 'route' => 'admin.master-data.show', 'params' => ['table' => 'pricing_bases'], 'icon' => 'tags', 'permission' => 'catalog.master_data.view'],
                ['label' => 'Work item categories', 'route' => 'admin.master-data.show', 'params' => ['table' => 'work_item_categories'], 'icon' => 'tags', 'permission' => 'catalog.master_data.view'],
                ['label' => 'Material categories', 'route' => 'admin.master-data.show', 'params' => ['table' => 'material_categories'], 'icon' => 'tags', 'permission' => 'catalog.master_data.view'],
            ]],
        ]],
        ['key' => 'projects', 'label' => 'Projects', 'icon' => 'folder-kanban', 'items' => [
            ['label' => 'Projects', 'route' => 'projects.projects.index', 'icon' => 'folder-kanban', 'permission' => 'projects.projects.view', 'mobile_primary' => true],
            ['label' => 'Tasks', 'route' => 'projects.tasks.index', 'icon' => 'list-checks', 'permission' => 'projects.tasks.view', 'mobile_primary' => true],
            ['label' => 'Approvals', 'route' => 'projects.approvals.index', 'icon' => 'stamp', 'permission' => 'projects.approvals.view'],
            ['label' => 'Task templates', 'route' => 'projects.templates.index', 'icon' => 'layout-template', 'permission' => 'projects.task_templates.manage'],
            ['label' => 'Master data', 'icon' => 'database', 'children' => [
                ['label' => 'Project types', 'route' => 'admin.master-data.show', 'params' => ['table' => 'project_types'], 'icon' => 'tags', 'permission' => 'projects.master_data.view'],
                ['label' => 'Project statuses', 'route' => 'admin.master-data.show', 'params' => ['table' => 'project_statuses'], 'icon' => 'tags', 'permission' => 'projects.master_data.view'],
                ['label' => 'Project phases', 'route' => 'admin.master-data.show', 'params' => ['table' => 'project_phases'], 'icon' => 'tags', 'permission' => 'projects.master_data.view'],
                ['label' => 'Project roles', 'route' => 'admin.master-data.show', 'params' => ['table' => 'project_roles'], 'icon' => 'tags', 'permission' => 'projects.master_data.view'],
                ['label' => 'Task types', 'route' => 'admin.master-data.show', 'params' => ['table' => 'task_types'], 'icon' => 'tags', 'permission' => 'projects.master_data.view'],
                ['label' => 'Task statuses', 'route' => 'admin.master-data.show', 'params' => ['table' => 'task_statuses'], 'icon' => 'tags', 'permission' => 'projects.master_data.view'],
                ['label' => 'Task priorities', 'route' => 'admin.master-data.show', 'params' => ['table' => 'task_priorities'], 'icon' => 'tags', 'permission' => 'projects.master_data.view'],
                ['label' => 'Approval authorities', 'route' => 'admin.master-data.show', 'params' => ['table' => 'approval_authorities'], 'icon' => 'landmark', 'permission' => 'projects.master_data.view'],
                ['label' => 'Approval types', 'route' => 'admin.master-data.show', 'params' => ['table' => 'approval_types'], 'icon' => 'tags', 'permission' => 'projects.master_data.view'],
                ['label' => 'Approval statuses', 'route' => 'admin.master-data.show', 'params' => ['table' => 'approval_statuses'], 'icon' => 'tags', 'permission' => 'projects.master_data.view'],
                ['label' => 'Hold reasons', 'route' => 'admin.master-data.show', 'params' => ['table' => 'hold_reasons'], 'icon' => 'tags', 'permission' => 'projects.master_data.view'],
            ]],
        ]],
        ['key' => 'estimation', 'label' => 'Estimation & Site', 'icon' => 'ruler', 'items' => []],
        ['key' => 'sales', 'label' => 'Sales', 'icon' => 'receipt', 'items' => []],
        ['key' => 'purchases', 'label' => 'Purchases', 'icon' => 'shopping-cart', 'items' => []],
        ['key' => 'accounting', 'label' => 'Accounting', 'icon' => 'landmark', 'items' => []],
        ['key' => 'hrm', 'label' => 'HRM', 'icon' => 'id-card', 'items' => [
            ['label' => 'Employees', 'route' => 'hrm.employees.index', 'icon' => 'id-card', 'permission' => 'hrm.employees.view_basic'],
            ['label' => 'Org chart', 'route' => 'hrm.org-chart', 'icon' => 'network', 'permission' => 'hrm.employees.view_basic'],
            ['label' => 'Expiring documents', 'route' => 'hrm.documents.expiring', 'icon' => 'file-clock', 'permission' => 'hrm.documents.manage'],
            ['label' => 'Master data', 'icon' => 'database', 'children' => [
                ['label' => 'Departments', 'route' => 'admin.master-data.show', 'params' => ['table' => 'departments'], 'icon' => 'network', 'permission' => 'hrm.masters.view'],
                ['label' => 'Designations', 'route' => 'admin.master-data.show', 'params' => ['table' => 'designations'], 'icon' => 'badge', 'permission' => 'hrm.masters.view'],
                ['label' => 'Employee types', 'route' => 'admin.master-data.show', 'params' => ['table' => 'employee_types'], 'icon' => 'tags', 'permission' => 'hrm.masters.view'],
                ['label' => 'Employee statuses', 'route' => 'admin.master-data.show', 'params' => ['table' => 'employee_statuses'], 'icon' => 'tags', 'permission' => 'hrm.masters.view'],
                ['label' => 'Genders', 'route' => 'admin.master-data.show', 'params' => ['table' => 'genders'], 'icon' => 'tags', 'permission' => 'hrm.masters.view'],
                ['label' => 'Marital statuses', 'route' => 'admin.master-data.show', 'params' => ['table' => 'marital_statuses'], 'icon' => 'tags', 'permission' => 'hrm.masters.view'],
                ['label' => 'Blood groups', 'route' => 'admin.master-data.show', 'params' => ['table' => 'blood_groups'], 'icon' => 'tags', 'permission' => 'hrm.masters.view'],
                ['label' => 'Document types', 'route' => 'admin.master-data.show', 'params' => ['table' => 'employee_document_types'], 'icon' => 'tags', 'permission' => 'hrm.masters.view'],
                ['label' => 'Employment event types', 'route' => 'admin.master-data.show', 'params' => ['table' => 'employment_event_types'], 'icon' => 'tags', 'permission' => 'hrm.masters.view'],
                ['label' => 'Exit reasons', 'route' => 'admin.master-data.show', 'params' => ['table' => 'exit_reasons'], 'icon' => 'tags', 'permission' => 'hrm.masters.view'],
            ]],
        ]],
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

    /*
    | Detail and create/edit routes that open in the detail modal at md and up when a link
    | carries `data-detail-modal` (App\Modules\Foundation\Livewire\Shared\DetailModal).
    | Below md they stay full-screen pages.
    */
    'detail_modal' => [
        'crm.leads.show', 'crm.leads.create', 'crm.leads.edit',
        'crm.customers.show', 'crm.customers.create', 'crm.customers.edit',
        'crm.teams.create', 'crm.teams.edit',
        'hrm.employees.show', 'hrm.employees.create', 'hrm.employees.edit',
        'projects.projects.create', 'projects.projects.edit',
        'projects.tasks.show', 'projects.tasks.create', 'projects.tasks.edit',
        'projects.templates.create', 'projects.templates.edit',
        'projects.approvals.show', 'projects.approvals.create', 'projects.approvals.edit',
        'catalog.services.create', 'catalog.services.edit',
        'catalog.work-items.create', 'catalog.work-items.edit',
        'catalog.materials.create', 'catalog.materials.edit',
        'admin.users.create', 'admin.users.edit',
        'admin.roles.create', 'admin.roles.edit',
    ],
];
