<?php

namespace App\Modules\Projects\Services;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectServiceStatus;
use App\Support\Lookups\ActiveLookup;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Project service lines (docs/04 §3.3, spec P5): validation, amount maths and replacing a
 * project's lines. Callers authorize, check the contract lock and hold the transaction.
 *
 * @phpstan-type ServiceLine array{id: int|null, service_id: int, description: string|null, quantity: string, unit_id: int|null, rate: string, discount_amount: string, amount: string, project_service_status_id: int}
 */
final class ServiceLines
{
    /**
     * amount = round(qty × rate, 2) − discount, half-up (CM-BR-07).
     */
    public static function amount(string $quantity, string $rate, string $discount): string
    {
        return (string) BigDecimal::of($quantity)->multipliedBy($rate)->toScale(2, RoundingMode::HalfUp)->minus(BigDecimal::of($discount)->toScale(2, RoundingMode::HalfUp));
    }

    /**
     * Σ amount of lines that are not cancelled (PRJ-BR-03).
     *
     * @param  iterable<array{amount: string, project_service_status_id: int}>  $lines
     */
    public static function total(iterable $lines): string
    {
        $cancelled = ProjectServiceStatus::idFor(ProjectServiceStatus::CANCELLED);
        $total = BigDecimal::zero();

        foreach ($lines as $line) {
            if ((int) $line['project_service_status_id'] !== $cancelled) {
                $total = $total->plus($line['amount']);
            }
        }

        return (string) $total->toScale(2);
    }

    /**
     * Validate and normalise submitted lines. Existing line ids must belong to the project.
     *
     * @param  list<int>  $currentServiceIds  services already on the record (allowed even if inactive)
     * @param  bool  $withStatus  take the delivery status from the input (amendments); otherwise
     *                            existing lines keep theirs and new lines start NOT_STARTED
     * @return list<ServiceLine>
     *
     * @throws ValidationException
     */
    public function validate(mixed $input, ?Project $project = null, array $currentServiceIds = [], string $key = 'services', bool $withStatus = false): array
    {
        $lines = array_values(array_map(fn (mixed $line): array => $this->clean(is_array($line) ? $line : []), is_array($input) ? $input : []));
        $existing = $project?->services()->pluck('project_service_status_id', 'id')->all() ?? [];
        $existingIds = array_keys($existing);

        $validator = Validator::make([$key => $lines], [
            $key => ['array', 'max:50'],
            "{$key}.*.id" => ['nullable', 'integer'],
            "{$key}.*.service_id" => ['required', 'integer', fn (string $attribute, mixed $value, \Closure $fail) => in_array((int) $value, $currentServiceIds, true) ? null : (new ActiveLookup('services'))->validate($attribute, $value, $fail)],
            "{$key}.*.description" => ['nullable', 'string', 'max:500'],
            "{$key}.*.quantity" => ['required', 'numeric', 'gt:0', 'max:99999999999999'],
            "{$key}.*.unit_id" => ['nullable', 'integer', 'exists:units,id'],
            "{$key}.*.rate" => ['required', 'numeric', 'min:0', 'max:99999999999999'],
            "{$key}.*.discount_amount" => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            "{$key}.*.project_service_status_id" => ['nullable', 'integer', 'exists:project_service_statuses,id'],
        ], [], [
            "{$key}.*.service_id" => __('service'),
            "{$key}.*.quantity" => __('quantity'),
            "{$key}.*.rate" => __('rate'),
            "{$key}.*.discount_amount" => __('discount'),
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($lines, $existingIds, $key): void {
            foreach ($lines as $index => $line) {
                if ($line['id'] !== null && ! in_array($line['id'], $existingIds, true)) {
                    $validator->errors()->add("{$key}.{$index}.id", __('This line does not belong to the project.'));
                }

                if (is_numeric($line['quantity']) && is_numeric($line['rate']) && is_numeric($line['discount_amount'])
                    && BigDecimal::of(self::amount((string) $line['quantity'], (string) $line['rate'], (string) $line['discount_amount']))->isNegative()) {
                    $validator->errors()->add("{$key}.{$index}.discount_amount", __('The discount is larger than the line value.'));
                }
            }
        });

        $validator->validate();

        $notStarted = ProjectServiceStatus::idFor(ProjectServiceStatus::NOT_STARTED);

        return array_map(fn (array $line): array => [
            'id' => $line['id'],
            'service_id' => (int) $line['service_id'],
            'description' => $line['description'],
            'quantity' => (string) $line['quantity'],
            'unit_id' => $line['unit_id'] !== null ? (int) $line['unit_id'] : null,
            'rate' => (string) $line['rate'],
            'discount_amount' => (string) $line['discount_amount'],
            'amount' => self::amount((string) $line['quantity'], (string) $line['rate'], (string) $line['discount_amount']),
            'project_service_status_id' => (int) ($withStatus
                ? ($line['project_service_status_id'] ?? $notStarted)
                : ($existing[$line['id']] ?? $notStarted)),
        ], $lines);
    }

    /**
     * Replace the project's lines and recalculate its contract value (and a draft contract's
     * deed amount). Must run inside the caller's transaction.
     *
     * @param  list<ServiceLine>  $lines
     */
    public function apply(Project $project, array $lines): void
    {
        $keep = array_values(array_filter(array_column($lines, 'id')));
        $project->services()->whereNotIn('id', $keep)->get()->each->delete();

        foreach ($lines as $index => $line) {
            $attributes = [...$line, 'sort_order' => $index + 1];
            unset($attributes['id']);

            $line['id'] !== null
                ? $project->services()->findOrFail($line['id'])->fill($attributes)->save()
                : $project->services()->create($attributes);
        }

        $project->forceFill(['contract_value' => self::total($lines)])->save();

        $contract = $project->contract()->with('status')->first();

        if ($contract !== null && $contract->isDraft()) {
            $contract->forceFill(['deed_amount' => $project->contract_value])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{id: int|null, service_id: mixed, description: string|null, quantity: mixed, unit_id: mixed, rate: mixed, discount_amount: mixed, project_service_status_id: mixed}
     */
    private function clean(array $line): array
    {
        $number = function (mixed $value, ?string $default = null): mixed {
            if (is_string($value)) {
                $value = str_replace(',', '', trim($value));
            }

            return $value === '' || $value === null ? $default : $value;
        };

        $description = is_string($line['description'] ?? null) ? trim($line['description']) : null;

        return [
            'id' => is_numeric($line['id'] ?? null) ? (int) $line['id'] : null,
            'service_id' => $line['service_id'] ?? null,
            'description' => $description === '' ? null : $description,
            'quantity' => $number($line['quantity'] ?? null, '1'),
            'unit_id' => $number($line['unit_id'] ?? null),
            'rate' => $number($line['rate'] ?? null, '0'),
            'discount_amount' => $number($line['discount_amount'] ?? null, '0'),
            'project_service_status_id' => $number($line['project_service_status_id'] ?? null),
        ];
    }
}
