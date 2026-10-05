<?php

namespace App\Support\Lookups;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

final class LookupRegistry
{
    /**
     * @param  array<string, array{label: string, module: string, permission: string, extra_fields?: list<string>}>  $tables
     */
    public function __construct(private array $tables) {}

    /**
     * @return array<string, array{label: string, module: string, permission: string, extra_fields?: list<string>}>
     */
    public function all(): array
    {
        return $this->tables;
    }

    /**
     * @return array{label: string, module: string, permission: string, extra_fields?: list<string>}
     */
    public function get(string $table): array
    {
        return $this->tables[$table]
            ?? throw new InvalidArgumentException("Lookup table [{$table}] is not registered.");
    }

    /**
     * Active options for a dropdown, plus any ids an existing record still references (CM-BR-03).
     *
     * @param  int|list<int>|null  $include
     * @return Collection<int, stdClass>
     */
    public function options(string $table, int|array|null $include = null): Collection
    {
        $this->get($table);

        $include = array_values(array_filter(Arr::wrap($include)));

        return DB::table($table)
            ->select(['id', 'code', 'name', 'color', 'is_active'])
            ->where(function (Builder $query) use ($include): void {
                $query->where('is_active', true);

                if ($include !== []) {
                    $query->orWhereIn('id', $include);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
