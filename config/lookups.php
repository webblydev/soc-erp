<?php

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Catalog\Models\MaterialCategory;
use App\Modules\Catalog\Models\PricingBasis;
use App\Modules\Catalog\Models\ServiceCategory;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\UnitKind;
use App\Modules\Catalog\Models\WorkItemCategory;
use App\Modules\Crm\Models\ActivityOutcome;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\CustomerStatus;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\LeadLevel;
use App\Modules\Crm\Models\LeadPriority;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Models\LostReason;
use App\Modules\Crm\Models\PaymentTerm;
use App\Modules\Foundation\Models\Branch;
use App\Modules\Foundation\Models\Currency;
use App\Modules\Foundation\Models\DocumentType;
use App\Modules\Foundation\Models\LocationLevel;
use App\Modules\Hrm\Models\BloodGroup;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\EmployeeDocumentType;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Models\EmployeeType;
use App\Modules\Hrm\Models\EmploymentEventType;
use App\Modules\Hrm\Models\ExitReason;
use App\Modules\Hrm\Models\Gender;
use App\Modules\Hrm\Models\MaritalStatus;

/*
| Registry of lookup tables edited through the generic Master Data screen (docs/01 §5.8).
| Each module appends its own tables. permission is a prefix: {prefix}.view|create|update|deactivate.
| extra_fields: column => [type (text|textarea|number|bool|list|lookup|employee), label]. list is edited as
| comma-separated text and stored as a JSON array of lowercase tokens; `in` names a config list
| the tokens must come from. A lookup field stores an id from `table`. Any field may add `rules`
| (extra Laravel rules), `unique` (unique in its table) and `uppercase` (stored upper-cased).
| An employee field stores an employee id; new values must be assignable employees (HR-BR-06).
| single_flags: bool columns only one row may hold. tree: the parent column of a self-referencing
| table; a row cannot be placed under itself or a descendant.
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
            'manager_employee_id' => ['type' => 'employee', 'label' => 'Manager'],
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
            'manager_employee_id' => ['type' => 'employee', 'label' => 'Manager'],
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
    'lead_sources' => [
        'label' => 'Lead sources', 'module' => 'crm', 'model' => LeadSource::class, 'permission' => 'crm.master_data',
        'extra_fields' => ['requires_referrer' => ['type' => 'bool', 'label' => 'Requires a referrer']],
    ],
    'lead_statuses' => [
        'label' => 'Lead statuses', 'module' => 'crm', 'model' => LeadStatus::class, 'permission' => 'crm.master_data',
        'extra_fields' => ['probability_pct' => ['type' => 'number', 'label' => 'Probability %', 'required' => true, 'rules' => ['max:100']]],
    ],
    'lead_priorities' => ['label' => 'Lead priorities', 'module' => 'crm', 'model' => LeadPriority::class, 'permission' => 'crm.master_data', 'extra_fields' => []],
    'lead_levels' => ['label' => 'Lead levels', 'module' => 'crm', 'model' => LeadLevel::class, 'permission' => 'crm.master_data', 'extra_fields' => []],
    'lost_reasons' => ['label' => 'Lost reasons', 'module' => 'crm', 'model' => LostReason::class, 'permission' => 'crm.master_data', 'extra_fields' => []],
    'activity_types' => [
        'label' => 'Activity types', 'module' => 'crm', 'model' => ActivityType::class, 'permission' => 'crm.master_data',
        'extra_fields' => [
            'icon' => ['type' => 'text', 'label' => 'Icon (lucide name)', 'rules' => ['max:40', 'regex:/^[a-z0-9-]+$/']],
            'requires_duration' => ['type' => 'bool', 'label' => 'Requires a duration'],
            'counts_as_contact' => ['type' => 'bool', 'label' => 'Counts as contact'],
        ],
    ],
    'activity_outcomes' => ['label' => 'Activity outcomes', 'module' => 'crm', 'model' => ActivityOutcome::class, 'permission' => 'crm.master_data', 'extra_fields' => []],
    'customer_types' => ['label' => 'Customer types', 'module' => 'crm', 'model' => CustomerType::class, 'permission' => 'crm.master_data', 'extra_fields' => []],
    'customer_statuses' => ['label' => 'Customer statuses', 'module' => 'crm', 'model' => CustomerStatus::class, 'permission' => 'crm.master_data', 'extra_fields' => []],
    'payment_terms' => [
        'label' => 'Payment terms', 'module' => 'crm', 'model' => PaymentTerm::class, 'permission' => 'crm.master_data',
        'extra_fields' => ['days' => ['type' => 'number', 'label' => 'Days', 'required' => true, 'rules' => ['max:365']]],
    ],
    'departments' => [
        'label' => 'Departments', 'module' => 'hrm', 'model' => Department::class, 'permission' => 'hrm.masters', 'tree' => 'parent_id',
        'extra_fields' => [
            'parent_id' => ['type' => 'lookup', 'table' => 'departments', 'label' => 'Parent department'],
            'head_employee_id' => ['type' => 'employee', 'label' => 'Head'],
        ],
    ],
    'designations' => [
        'label' => 'Designations', 'module' => 'hrm', 'model' => Designation::class, 'permission' => 'hrm.masters',
        'extra_fields' => [
            'grade' => ['type' => 'text', 'label' => 'Grade', 'rules' => ['max:20']],
            'department_id' => ['type' => 'lookup', 'table' => 'departments', 'label' => 'Department'],
        ],
    ],
    'employee_types' => ['label' => 'Employee types', 'module' => 'hrm', 'model' => EmployeeType::class, 'permission' => 'hrm.masters', 'extra_fields' => []],
    'employee_statuses' => ['label' => 'Employee statuses', 'module' => 'hrm', 'model' => EmployeeStatus::class, 'permission' => 'hrm.masters', 'extra_fields' => []],
    'genders' => ['label' => 'Genders', 'module' => 'hrm', 'model' => Gender::class, 'permission' => 'hrm.masters', 'extra_fields' => []],
    'marital_statuses' => ['label' => 'Marital statuses', 'module' => 'hrm', 'model' => MaritalStatus::class, 'permission' => 'hrm.masters', 'extra_fields' => []],
    'blood_groups' => ['label' => 'Blood groups', 'module' => 'hrm', 'model' => BloodGroup::class, 'permission' => 'hrm.masters', 'extra_fields' => []],
    'employee_document_types' => [
        'label' => 'Employee document types', 'module' => 'hrm', 'model' => EmployeeDocumentType::class, 'permission' => 'hrm.masters',
        'extra_fields' => ['has_expiry' => ['type' => 'bool', 'label' => 'Has an expiry date']],
    ],
    'employment_event_types' => ['label' => 'Employment event types', 'module' => 'hrm', 'model' => EmploymentEventType::class, 'permission' => 'hrm.masters', 'extra_fields' => []],
    'exit_reasons' => ['label' => 'Exit reasons', 'module' => 'hrm', 'model' => ExitReason::class, 'permission' => 'hrm.masters', 'extra_fields' => []],
];
