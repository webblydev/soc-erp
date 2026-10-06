<?php

use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\Currency;
use App\Modules\Foundation\Models\DocumentType;
use App\Modules\Foundation\Models\LocationLevel;

/*
| Registry of lookup tables edited through the generic Master Data screen (docs/01 §5.8).
| Each module appends its own tables. permission is a prefix: {prefix}.view|create|update|deactivate.
| extra_fields: column => [type (text|textarea|number|bool|list), label]. list is edited as
| comma-separated text and stored as a JSON array of lowercase tokens; `in` names a config list
| the tokens must come from. single_flags: bool columns only one row may hold.
*/

return [
    'branches' => [
        'label' => 'Branches',
        'module' => 'admin',
        'model' => Branch::class,
        'permission' => 'admin.branches',
        'extra_fields' => [
            'address' => ['type' => 'textarea', 'label' => 'Address'],
            'phone' => ['type' => 'text', 'label' => 'Phone'],
            'is_head_office' => ['type' => 'bool', 'label' => 'Head office'],
        ],
        'single_flags' => ['is_head_office'],
    ],
    'currencies' => [
        'label' => 'Currencies',
        'module' => 'admin',
        'model' => Currency::class,
        'permission' => 'admin.master_data',
        'extra_fields' => [
            'symbol' => ['type' => 'text', 'label' => 'Symbol', 'required' => true],
            'decimal_places' => ['type' => 'number', 'label' => 'Decimal places', 'required' => true],
            'is_base' => ['type' => 'bool', 'label' => 'Base currency'],
        ],
        'single_flags' => ['is_base'],
    ],
    'location_levels' => [
        'label' => 'Location levels',
        'module' => 'admin',
        'model' => LocationLevel::class,
        'permission' => 'admin.locations',
        'extra_fields' => [],
    ],
    'document_types' => [
        'label' => 'Document types',
        'module' => 'admin',
        'model' => DocumentType::class,
        'permission' => 'admin.master_data',
        'extra_fields' => [
            'allowed_mimes' => ['type' => 'list', 'label' => 'Allowed extensions (blank allows all)', 'in' => 'foundation.attachments.extensions'],
            'max_size_mb' => ['type' => 'number', 'label' => 'Max size (MB)', 'required' => true],
        ],
    ],
];
