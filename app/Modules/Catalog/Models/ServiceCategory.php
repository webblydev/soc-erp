<?php

namespace App\Modules\Catalog\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Catalog\ServiceCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A grouping of services (docs/02 §3.2).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system'])]
#[UseFactory(ServiceCategoryFactory::class)]
class ServiceCategory extends Model
{
    /** @use HasFactory<ServiceCategoryFactory> */
    use Auditable, HasFactory, IsLookup;
}
