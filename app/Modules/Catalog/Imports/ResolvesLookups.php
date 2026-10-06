<?php

namespace App\Modules\Catalog\Imports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Resolves a spreadsheet cell to an active lookup row by any of the given columns, ignoring case.
 * Each table is loaded once per import.
 */
trait ResolvesLookups
{
    /** @var array<string, array<string, int>> */
    private array $lookupMaps = [];

    /**
     * @param  list<string>  $columns
     */
    protected function resolveLookup(string $table, string $value, array $columns = ['code', 'name']): ?int
    {
        $this->lookupMaps[$table] ??= $this->loadLookupMap($table, $columns);

        return $this->lookupMaps[$table][Str::lower(trim($value))] ?? null;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, int>
     */
    private function loadLookupMap(string $table, array $columns): array
    {
        $map = [];

        foreach (DB::table($table)->where('is_active', true)->whereNull('deleted_at')->orderBy('sort_order')->get(['id', ...$columns]) as $row) {
            foreach ($columns as $column) {
                $map[Str::lower((string) $row->{$column})] ??= (int) $row->id;
            }
        }

        return $map;
    }
}
