<?php

namespace App\Modules\Estimation\Actions;

use App\Models\User;
use App\Modules\Estimation\Models\ProjectBudgetLine;
use App\Modules\Estimation\Services\BudgetRevisions;
use App\Modules\Projects\Models\Project;
use App\Support\Lookups\ActiveLookup;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Saves the hand-made budget lines of a project as a grid (docs/05 §5.4, spec E11). Lines built
 * from estimates are left alone. Amounts cannot be negative, and once the budget has a revision a
 * change of the total needs a reason (ES-BR-05).
 */
class SaveBudget
{
    public function __construct(private BudgetRevisions $revisions) {}

    /**
     * @param  array<int, mixed>  $lines  [{id?, cost_category_id, work_item_id, material_id, material_name, description, budget_qty, unit_id, budget_amount}]
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Project $project, array $lines, ?string $reason = null): void
    {
        Gate::forUser($actor)->authorize('manageBudget', $project);

        $existing = $project->budgetLines()->whereNull('source_estimate_id')->get()->keyBy('id');
        $lines = array_values(array_map(fn (mixed $line): array => self::clean(is_array($line) ? $line : []), array_filter($lines, 'is_array')));
        $reason = is_string($reason) && trim($reason) !== '' ? trim($reason) : null;

        $rules = [
            'lines' => ['array', 'max:200'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.material_name' => ['nullable', 'string', 'max:200'],
            'lines.*.budget_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'lines.*.budget_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];

        foreach ($lines as $index => $line) {
            $current = $line['id'] !== null ? $existing->get($line['id']) : null;
            $rules["lines.{$index}.cost_category_id"] = ['required', new ActiveLookup('cost_categories', $current?->cost_category_id)];
            $rules["lines.{$index}.work_item_id"] = ['nullable', new ActiveLookup('work_items', $current?->work_item_id)];
            $rules["lines.{$index}.material_id"] = ['nullable', new ActiveLookup('materials', $current?->material_id)];
            $rules["lines.{$index}.unit_id"] = ['nullable', new ActiveLookup('units', $current?->unit_id)];
        }

        $validator = Validator::make(['lines' => $lines, 'reason' => $reason], $rules, [], [
            'lines.*.cost_category_id' => __('cost category'),
            'lines.*.budget_amount' => __('amount'),
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($lines, $existing, $project, $reason): void {
            foreach ($lines as $index => $line) {
                if ($line['id'] !== null && ! $existing->has($line['id'])) {
                    $validator->errors()->add("lines.{$index}.id", __('This line cannot be edited here.'));
                }
            }

            if ($reason === null && $this->revisions->isApproved($project) && ! $validator->errors()->any()
                && ! BigDecimal::of($this->newTotal($project, $lines))->isEqualTo($this->revisions->total($project))) {
                $validator->errors()->add('reason', __('Give a reason for changing the approved budget.'));
            }
        });

        $validator->validate();

        DB::transaction(function () use ($actor, $project, $lines, $existing, $reason): void {
            $oldTotal = $this->revisions->total($project);
            $keep = array_values(array_filter(array_column($lines, 'id')));
            $project->budgetLines()->whereNull('source_estimate_id')->whereNotIn('id', $keep)->delete();
            $order = (int) $project->budgetLines()->whereNotNull('source_estimate_id')->max('sort_order');

            foreach ($lines as $line) {
                $attributes = [...$line, 'sort_order' => ++$order];
                unset($attributes['id']);

                /** @var ProjectBudgetLine $model */
                $model = $line['id'] !== null ? $existing->get($line['id']) : $project->budgetLines()->make();
                $model->fill($attributes)->save();
            }

            $this->revisions->record($project, $oldTotal, $reason, $actor);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function newTotal(Project $project, array $lines): string
    {
        $built = $project->budgetLines()->whereNotNull('source_estimate_id')->pluck('budget_amount')
            ->reduce(fn (BigDecimal $sum, mixed $amount): BigDecimal => $sum->plus((string) $amount), BigDecimal::zero());

        return (string) array_reduce($lines, fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus((string) $line['budget_amount']), $built);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private static function clean(array $line): array
    {
        $value = function (mixed $value): mixed {
            if (is_string($value)) {
                $value = trim($value);

                return $value === '' ? null : str_replace(',', '', $value);
            }

            return $value;
        };

        $text = fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null;

        return [
            'id' => is_numeric($line['id'] ?? null) ? (int) $line['id'] : null,
            'cost_category_id' => $value($line['cost_category_id'] ?? null),
            'work_item_id' => $value($line['work_item_id'] ?? null),
            'material_id' => $value($line['material_id'] ?? null),
            'material_name' => $text($line['material_name'] ?? null),
            'description' => $text($line['description'] ?? null),
            'budget_qty' => $value($line['budget_qty'] ?? null),
            'unit_id' => $value($line['unit_id'] ?? null),
            'budget_amount' => $value($line['budget_amount'] ?? null),
        ];
    }
}
