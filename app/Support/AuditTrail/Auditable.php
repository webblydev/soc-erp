<?php

namespace App\Support\AuditTrail;

use App\Modules\Foundation\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Auditable
{
    /**
     * Register the audit callbacks. Model::observe() cannot be called while the model is booting.
     */
    public static function bootAuditable(): void
    {
        $observer = new AuditObserver;

        static::created($observer->created(...));
        static::updated($observer->updated(...));
        static::deleted($observer->deleted(...));

        if (method_exists(static::class, 'restored')) {
            static::restored($observer->restored(...));
        }
    }

    /**
     * Attributes never written to the audit log.
     *
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return array_values(array_unique([
            ...$this->getHidden(),
            'created_at',
            'updated_at',
            'deleted_at',
            'created_by',
            'updated_by',
        ]));
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }
}
