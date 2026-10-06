<?php

namespace Database\Seeders\Legacy;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * State shared by the importers of one LegacyDataSeeder run: legacy id → new id maps and cached
 * lookup ids.
 */
class LegacyContext
{
    /** @var array<int, int> legacy employee id → employee id */
    public array $employees = [];

    /** @var array<int, int> legacy user id → user id */
    public array $users = [];

    /** @var array<string, int> lower-cased legacy username → user id */
    public array $usernames = [];

    /** @var array<int, int> user id → sales team id */
    public array $teamOfUser = [];

    /** @var array<int, array{lead: int, customer: int|null, owner: int, open: bool}> legacy client id → lead */
    public array $leads = [];

    /** @var array<string, int> */
    private array $lookupIds = [];

    public function legacy(): ConnectionInterface
    {
        return DB::connection('legacy');
    }

    /**
     * Id of a lookup row by code, cached for the run.
     *
     * @param  class-string<Model>  $model
     */
    public function idFor(string $model, string $code): ?int
    {
        $key = $model.':'.$code;

        if (! array_key_exists($key, $this->lookupIds)) {
            $id = $model::query()->where('code', $code)->value('id');
            $this->lookupIds[$key] = $id === null ? 0 : (int) $id;
        }

        return $this->lookupIds[$key] ?: null;
    }
}
