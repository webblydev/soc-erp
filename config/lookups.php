<?php

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\MaterialCategory;
use App\Modules\Catalog\Models\PricingBasis;
use App\Modules\Catalog\Models\ServiceCategory;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\UnitKind;
use App\Modules\Catalog\Models\WorkItemCategory;
use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\Currency;
use App\Modules\Foundation\Models\DocumentType;
use App\Modules\Foundation\Models\LocationLevel;

/*
| Registry of lookup tables edited through the generic Master Data screen (docs/01 §5.8).
| Each module appends its own tables. permission is a prefix: {prefix}.view|create|update|deactivate.
| extra_fields: column => [type (text|textarea|number|bool|list), label]. list is edited as
| comma-separated text and stored as a JSON array of lowercase tokens; `in` names a config list
| the tokens must come from. A lookup field stores an id from `table`. Any field may add `rules`
| (extra Laravel rules), `unique` (unique in its table) and `uppercase` (stored upper-cased).
| single_flags: bool columns only one row may hold.
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
    'business_lines' => [
        'label' => 'Business lines',
        'module' => 'catalog',
        'model' => BusinessLine::class,
        'permission' => 'catalog.business_lines',
        'extra_fields' => [
            'project_prefix' => ['type' => 'text', 'label' => 'Project number prefix', 'required' => true, 'uppercase' => true, 'unique' => true, 'rules' => ['max:30', 'regex:/^[A-Z0-9&-]+$/']],
            'is_internal' => ['type' => 'bool', 'label' => 'Internal (not sellable)'],
        ],
    ],
    'service_categories' => ['label' => 'Service categories', 'module' => 'catalog', 'model' => ServiceCategory::class, 'permission' => 'catalog.master_data', 'extra_fields' => []],
    'pricing_bases' => ['label' => 'Pricing bases', 'module' => 'catalog', 'model' => PricingBasis::class, 'permission' => 'catalog.master_data', 'extra_fields' => []],
    'units' => [
        'label' => 'Units',
        'module' => 'catalog',
        'model' => Unit::class,
        'permission' => 'catalog.units',
        'extra_fields' => [
            'symbol' => ['type' => 'text', 'label' => 'Symbol', 'required' => true, 'rules' => ['max:15']],
            'unit_kind_id' => ['type' => 'lookup', 'table' => 'unit_kinds', 'label' => 'Kind', 'required' => true],
        ],
    ],
    'unit_kinds' => ['label' => 'Unit kinds', 'module' => 'catalog', 'model' => UnitKind::class, 'permission' => 'catalog.master_data', 'extra_fields' => []],
    'work_item_categories' => ['label' => 'Work item categories', 'module' => 'catalog', 'model' => WorkItemCategory::class, 'permission' => 'catalog.master_data', 'extra_fields' => []],
    'material_categories' => ['label' => 'Material categories', 'module' => 'catalog', 'model' => MaterialCategory::class, 'permission' => 'catalog.master_data', 'extra_fields' => []],
];
