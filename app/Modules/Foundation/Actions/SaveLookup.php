<?php

namespace App\Modules\Foundation\Actions;

use App\Support\Lookups\ActiveLookup;
use App\Support\Lookups\LookupRegistry;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a row in any registered lookup table (docs/01 §5.8, FD-BR-05). Extra fields
 * may add `rules`, be `unique` in their table, be stored `uppercase`, or be a `lookup` (an id
 * from another lookup table, which must be active unless the row already holds it).
 */
class SaveLookup
{
    public const COLORS = ['neutral', 'info', 'success', 'warning', 'danger'];

    public function __construct(private LookupRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(string $table, array $input, ?Model $row = null): Model
    {
        $input = array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $input);

        $entry = $this->registry->get($table);
        $row ??= $this->registry->modelFor($table);
        $input = $this->splitListFields($entry['extra_fields'], $input);

        foreach ($entry['extra_fields'] as $field => $definition) {
            if (($definition['uppercase'] ?? false) && is_string($input[$field] ?? null)) {
                $input[$field] = Str::upper(trim($input[$field]));
            }
        }

        $rules = [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique($table, 'code')->ignore($row->getKey())],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(self::COLORS)],
            'is_active' => ['boolean'],
        ];

        foreach ($entry['extra_fields'] as $field => $definition) {
            $presence = ($definition['required'] ?? false) ? 'required' : 'nullable';

            $rules[$field] = match ($definition['type']) {
                'bool' => ['boolean'],
                'number' => [$presence, 'integer', 'min:0', 'max:65535'],
                'textarea' => [$presence, 'string', 'max:2000'],
                'list' => [$presence, 'array'],
                'lookup' => [$presence, new ActiveLookup((string) ($definition['table'] ?? ''), $row->getAttribute($field))],
                default => [$presence, 'string', 'max:255'],
            };

            if ($definition['unique'] ?? false) {
                $rules[$field][] = Rule::unique($table, $field)->ignore($row->getKey());
            }

            array_push($rules[$field], ...($definition['rules'] ?? []));

            if ($definition['type'] === 'list' && isset($definition['in'])) {
                $allowed = (array) config($definition['in']);
                $rules[$field][] = function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
                    $unknown = array_diff((array) $value, $allowed);

                    if ($unknown !== []) {
                        $fail(__('Not allowed: :values.', ['values' => implode(', ', $unknown)]));
                    }
                };
            }
        }

        /** @var array<string, mixed> $data */
        $data = Validator::make($input, $rules)->validate();

        if ($row->exists && $row->getAttribute('is_system')) {
            if ($data['code'] !== $row->getAttribute('code')) {
                throw ValidationException::withMessages(['code' => __('System rows cannot have their code changed.')]);
            }

            if (! ($data['is_active'] ?? true)) {
                throw ValidationException::withMessages(['is_active' => __('System rows cannot be deactivated.')]);
            }
        }

        return DB::transaction(function () use ($row, $data, $entry): Model {
            $row->fill(Arr::only($data, ['code', 'name', 'description', 'color', 'is_active', ...array_keys($entry['extra_fields'])]));

            if (! $row->exists) {
                $row->setAttribute('sort_order', (int) $row->newQuery()->max('sort_order') + 1);
                $row->setAttribute('is_system', false);
            }

            $row->save();

            foreach ($entry['single_flags'] ?? [] as $flag) {
                if ($row->getAttribute($flag)) {
                    $row->newQuery()->whereKeyNot($row->getKey())->where($flag, true)->get()
                        ->each(fn (Model $other) => $other->update([$flag => false]));
                }
            }

            return $row;
        });
    }

    /**
     * Turn comma-separated text for `list` fields into lowercase tokens; an empty list is null.
     *
     * @param  array<string, array{type: string, label: string, required?: bool, in?: string, table?: string, rules?: list<mixed>, unique?: bool, uppercase?: bool}>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function splitListFields(array $fields, array $input): array
    {
        foreach ($fields as $field => $definition) {
            if ($definition['type'] !== 'list' || ! is_string($input[$field] ?? null)) {
                continue;
            }

            $tokens = array_values(array_filter(array_map(
                fn (string $token): string => Str::lower(trim($token)),
                explode(',', $input[$field]),
            ), fn (string $token): bool => $token !== ''));

            $input[$field] = $tokens === [] ? null : $tokens;
        }

        return $input;
    }
}
