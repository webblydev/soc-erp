<?php

/*
| Registry of lookup tables edited through the generic Master Data screen (docs/01 §5.8).
| Each module appends its own lookup tables here.
*/

return [
    'branches' => [
        'label' => 'Branches',
        'module' => 'admin',
        'permission' => 'admin.branches',
        'extra_fields' => ['address', 'phone', 'is_head_office'],
    ],
    'currencies' => [
        'label' => 'Currencies',
        'module' => 'admin',
        'permission' => 'admin.master_data',
        'extra_fields' => ['symbol', 'decimal_places', 'is_base'],
    ],
];
