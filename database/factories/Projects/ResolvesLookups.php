<?php

namespace Database\Factories\Projects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Seeded lookup rows by code, created on the fly when the Projects seeder has not run.
 */
trait ResolvesLookups
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $attributes
     */
    protected static function lookupId(string $model, string $code, array $attributes = []): int
    {
        return (int) ($model::query()->where('code', $code)->value('id')
            ?? $model::query()->create(['code' => $code, 'name' => Str::headline(Str::lower($code)), 'is_system' => true, ...$attributes])->getKey());
    }
}
