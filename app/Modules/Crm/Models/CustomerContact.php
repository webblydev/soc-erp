<?php

namespace App\Modules\Crm\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Database\Factories\Crm\CustomerContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A person to talk to at a customer (docs/03 §3.10). One primary per customer (SaveCustomer).
 *
 * @property int $id
 * @property int $customer_id
 * @property string $name
 * @property string|null $designation
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_primary
 * @property string|null $notes
 * @property-read Customer $customer
 */
#[Fillable(['customer_id', 'name', 'designation', 'phone', 'email', 'is_primary', 'notes'])]
#[UseFactory(CustomerContactFactory::class)]
class CustomerContact extends Model
{
    /** @use HasFactory<CustomerContactFactory> */
    use Auditable, HasFactory, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
