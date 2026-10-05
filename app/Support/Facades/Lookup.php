<?php

namespace App\Support\Facades;

use App\Support\Lookups\LookupRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection<int, \stdClass> options(string $table, int|list<int>|null $include = null)
 * @method static array<string, array<string, mixed>> all()
 * @method static array<string, mixed> get(string $table)
 * @method static array<string, array<string, mixed>> visibleTo(\App\Models\User $user)
 * @method static bool allows(\App\Models\User $user, string $table, string $action)
 * @method static \Illuminate\Database\Eloquent\Model modelFor(string $table)
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
