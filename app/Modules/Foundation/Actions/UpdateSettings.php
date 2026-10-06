<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\Setting;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saves one settings group (docs/01 §5.7). Each value is validated by its declared type.
 */
class UpdateSettings
{
    /**
     * Inclusive bounds for integer settings, keyed by full setting key.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    public const INTEGER_BOUNDS = [
        'general.session_timeout_minutes' => [5, 1440],
        'general.password_min_length' => [8, 128],
    ];

    public function __construct(private SettingsRepository $settings) {}

    /**
     * @param  array<string, mixed>  $values  short key => value
     *
     * @throws ValidationException
     */
    public function handle(string $group, array $values): void
    {
        $types = Setting::query()->where('group', $group)->pluck('type', 'key');
        $rules = [];

        foreach (array_keys($values) as $key) {
            $type = $types[$key] ?? null;

            $rules[$key] = match (true) {
                $type === null => [fn (string $attribute, mixed $value, \Closure $fail) => $fail(__('Unknown setting.'))],
                $type === 'int' => ['required', 'integer'],
                $type === 'decimal' => ['required', 'numeric'],
                $type === 'bool' => ['boolean'],
                $type === 'json' => ['nullable', 'json'],
                str_starts_with($type, 'fk:') => ['nullable', 'integer', Rule::exists(substr($type, 3), 'id')],
                default => ['nullable', 'string', 'max:255'],
            };

            if (isset(self::INTEGER_BOUNDS[$group.'.'.$key])) {
                [$min, $max] = self::INTEGER_BOUNDS[$group.'.'.$key];
                $rules[$key] = [...$rules[$key], 'min:'.$min, 'max:'.$max];
            }
        }

        $validated = Validator::make($values, $rules)->validate();

        DB::transaction(function () use ($group, $validated, $types): void {
            foreach ($validated as $key => $value) {
                if (($types[$key] ?? null) === 'json' && is_string($value)) {
                    $value = json_decode($value, true);
                }

                $this->settings->set($group.'.'.$key, $value);
            }
        });
    }
}
