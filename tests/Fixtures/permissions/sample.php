<?php

return [
    'permissions' => [
        'crm' => ['leads' => ['view_own', 'view_all', 'create']],
        'notes' => ['' => ['create']],
    ],
    'grants' => [
        'sales_executive' => ['crm.leads.view_own', 'notes.*'],
        'viewer' => ['*.view_all'],
    ],
];
