<?php

namespace App\Support\Facades;

use App\Support\Lookups\LookupRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection<int, \stdClass> options(string $table, int|list<int>|null $include = null)
 * @method static array<string, array{label: string, module: string, permission: string, extra_fields?: list<string>}> all()
 * @method static array{label: string, module: string, permission: string, extra_fields?: list<string>} get(string $table)
 *
 * @see LookupRegistry
 */
class Lookup extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LookupRegistry::class;
    }
}
