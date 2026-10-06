<?php

namespace App\Support\Lookups;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

/**
 * @phpstan-type LookupEntry array{label: string, module: string, model: class-string<Model>, permission: string, extra_fields: array<string, array{type: string, label: string, required?: bool, in?: string, table?: string, rules?: list<mixed>, unique?: bool, uppercase?: bool}>, single_flags?: list<string>, tree?: string}
 */
final class LookupRegistry
{
    /**
     * @param  array<string, LookupEntry>  $tables
     */
    public function __construct(private array $tables) {}

    /**
     * @return array<string, LookupEntry>
     */
    public function all(): array
    {
        return $this->tables;
    }

    /**
     * @return LookupEntry
     */
    public function get(string $table): array
    {
        return $this->tables[$table]
            ?? throw new InvalidArgumentException("Lookup table [{$table}] is not registered.");
    }

    /**
     * Tables the user may open in Master Data, in registry order.
     *
     * @return array<string, LookupEntry>
     */
    public function visibleTo(User $user): array
    {
        return array_filter($this->tables, fn (array $entry): bool => $user->can($entry['permission'].'.view'));
    }

    public function allows(User $user, string $table, string $action): bool
    {
        return $user->can($this->get($table)['permission'].'.'.$action);
    }

    public function modelFor(string $table): Model
    {
        $class = $this->get($table)['model'];

        return new $class;
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
