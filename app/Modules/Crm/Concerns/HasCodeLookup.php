<?php

namespace App\Modules\Crm\Concerns;

use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Id of a seeded lookup row by its code, for code that depends on system rows.
 */
trait HasCodeLookup
{
    public static function idFor(string $code): int
    {
        $id = static::query()->where('code', $code)->value('id');

        if ($id === null) {
            throw (new ModelNotFoundException)->setModel(static::class, [$code]);
        }

        return (int) $id;
    }
}
