<?php

namespace App\Support\AuditTrail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditObserver
{
    public function created(Model $model): void
    {
        AuditTrail::record($model, 'created', null, AuditTrail::filter($model, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $new = AuditTrail::filter($model, $model->getChanges());

        if ($new === []) {
            return;
        }

        AuditTrail::record($model, 'updated', Arr::only($model->getPrevious(), array_keys($new)), $new);
    }

    public function deleted(Model $model): void
    {
        AuditTrail::record($model, 'deleted', AuditTrail::filter($model, $model->getAttributes()));
    }

    public function restored(Model $model): void
    {
        AuditTrail::record($model, 'restored');
    }
}
