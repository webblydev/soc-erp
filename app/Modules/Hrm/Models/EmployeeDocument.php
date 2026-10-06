<?php

namespace App\Modules\Hrm\Models;

use App\Modules\Foundation\Models\Attachment;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Database\Factories\Hrm\EmployeeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An employee document with optional expiry (docs/09 §3.3). Its file is an attachment on the
 * employee (spec H13).
 *
 * @property int $id
 * @property int $employee_id
 * @property int $employee_document_type_id
 * @property string|null $document_no
 * @property Carbon|null $issue_date
 * @property Carbon|null $expiry_date
 * @property int|null $attachment_id
 * @property string|null $notes
 * @property Carbon|null $expiry_notified_at
 * @property-read Employee $employee
 * @property-read EmployeeDocumentType $type
 * @property-read Attachment|null $attachment
 */
#[Fillable(['employee_document_type_id', 'document_no', 'issue_date', 'expiry_date', 'notes'])]
#[UseFactory(EmployeeDocumentFactory::class)]
class EmployeeDocument extends Model
{
    /** @use HasFactory<EmployeeDocumentFactory> */
    use Auditable, HasFactory, TracksAuthors;

    public const EXPIRING_DAYS = 30;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issue_date' => 'date', 'expiry_date' => 'date', 'expiry_notified_at' => 'datetime'];
    }

    /**
     * 'expired', 'expiring' (within 30 days), 'valid', or 'none' without an expiry date.
     */
    public function expiryState(): string
    {
        return match (true) {
            $this->expiry_date === null => 'none',
            $this->expiry_date->lt(today()) => 'expired',
            $this->expiry_date->lte(today()->addDays(self::EXPIRING_DAYS)) => 'expiring',
            default => 'valid',
        };
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<EmployeeDocumentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocumentType::class, 'employee_document_type_id');
    }

    /**
     * @return BelongsTo<Attachment, $this>
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }
}
