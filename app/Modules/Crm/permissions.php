<?php

/*
| CRM permissions (docs/03 §2, spec §4.1) and default role grants.
| view_own / view_team / view_all are data scopes (docs/00 §6); the gates crm.{leads|customers|activities}.view
| mean "any of the three". master_data covers the CRM lookups edited on the Master Data screen.
*/

$lookup = ['view', 'create', 'update', 'deactivate'];
$scopes = ['view_own', 'view_team', 'view_all'];

return [
    'permissions' => [
        'crm' => [
            'leads' => [...$scopes, 'create', 'update', 'delete', 'assign', 'convert', 'export', 'import'],
            'activities' => [...$scopes, 'create', 'update', 'delete'],
            'customers' => [...$scopes, 'create', 'update', 'update_finance', 'delete', 'export', 'merge'],
            'teams' => ['view', 'manage'],
            'master_data' => $lookup,
            'reports' => ['view'],
        ],
    ],
    'grants' => [
        'management' => [
            'crm.*.view_all',
            'crm.leads.create', 'crm.leads.update', 'crm.leads.delete', 'crm.leads.assign', 'crm.leads.convert', 'crm.leads.export', 'crm.leads.import',
            'crm.activities.create', 'crm.activities.update', 'crm.activities.delete',
            'crm.customers.create', 'crm.customers.update', 'crm.customers.update_finance', 'crm.customers.delete', 'crm.customers.export', 'crm.customers.merge',
            'crm.teams.*', 'crm.master_data.*', 'crm.reports.view',
        ],
        'sales_manager' => [
            'crm.leads.view_team', 'crm.leads.create', 'crm.leads.update', 'crm.leads.assign', 'crm.leads.convert', 'crm.leads.export',
            'crm.activities.view_team', 'crm.activities.create', 'crm.activities.update', 'crm.activities.delete',
            'crm.customers.view_team', 'crm.customers.create', 'crm.customers.update', 'crm.customers.export',
            'crm.teams.view', 'crm.master_data.view',
        ],
        'sales_executive' => [
            'crm.leads.view_own', 'crm.leads.create', 'crm.leads.update', 'crm.leads.convert',
            'crm.activities.view_own', 'crm.activities.create', 'crm.activities.update',
            'crm.customers.view_own', 'crm.customers.create', 'crm.customers.update',
        ],
        'project_manager' => ['crm.customers.view_all', 'crm.activities.view_own', 'crm.activities.create', 'crm.activities.update'],
        'engineer' => ['crm.customers.view_own', 'crm.activities.view_own', 'crm.activities.create', 'crm.activities.update'],
        'accountant' => ['crm.customers.view_all', 'crm.customers.update_finance', 'crm.customers.export'],
        'finance_manager' => ['crm.customers.view_all', 'crm.customers.update_finance', 'crm.customers.export'],
    ],
];
