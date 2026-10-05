<?php

namespace App\Modules\Foundation\Actions;

use App\Support\Lookups\LookupRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Moves a lookup row to a zero-based position (wire:sort) and renumbers sort_order 1..n.
 */
class ReorderLookup
{
    public function __construct(private LookupRegistry $registry) {}

    public function handle(string $table, int $id, int $position): void
    {
        $model = $this->registry->modelFor($table);
        $model->newQuery()->findOrFail($id);

        DB::transaction(function () use ($model, $id, $position): void {
            $ids = $model->newQuery()->orderBy('sort_order')->orderBy('name')->pluck('id')
                ->reject(fn (int $other): bool => $other === $id)->values()->all();

            array_splice($ids, max(0, min($position, count($ids))), 0, [$id]);

            foreach ($ids as $index => $rowId) {
                $model->newQuery()->whereKey($rowId)->update(['sort_order' => $index + 1]);
            }
        });
    }
}
