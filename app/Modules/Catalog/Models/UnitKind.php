<?php

namespace App\Modules\Catalog\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Catalog\UnitKindFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What a unit measures: length, area, volume… (docs/02 §3.4).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system'])]
#[UseFactory(UnitKindFactory::class)]
class UnitKind extends Model
{
    /** @use HasFactory<UnitKindFactory> */
    use Auditable, HasFactory, IsLookup;
}
