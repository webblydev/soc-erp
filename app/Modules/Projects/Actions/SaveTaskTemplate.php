<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Modules\Projects\Models\TaskTemplate;
use App\Support\Lookups\ActiveLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Creates or edits a task template with its items (docs/04 §5.8, spec P17). An item may depend on
 * an earlier item, given by its position (`depends_on`, 0-based). Checklists are one item per line.
 */
class SaveTaskTemplate
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $input, ?TaskTemplate $template = null): TaskTemplate
    {
        Gate::forUser($actor)->authorize('projects.task_templates.manage');

        $items = array_values(array_map(fn (mixed $item): array => $this->cleanItem(is_array($item) ? $item : []), is_array($input['items'] ?? null) ? $input['items'] : []));
        $ownIds = $template?->items()->pluck('id')->all() ?? [];

        $validator = Validator::make([...$input, 'items' => $items], [
            'name' => ['required', 'string', 'max:150'],
            'service_id' => ['nullable', new ActiveLookup('services', $template?->service_id)],
            'project_type_id' => ['nullable', new ActiveLookup('project_types', $template?->project_type_id)],
            'is_active' => ['boolean'],
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.task_type_id' => ['required', 'integer', 'exists:task_types,id'],
            'items.*.project_phase_id' => ['nullable', 'integer', 'exists:project_phases,id'],
            'items.*.project_role_id' => ['nullable', 'integer', 'exists:project_roles,id'],
            'items.*.offset_days_start' => ['required', 'integer', 'min:0', 'max:3650'],
            'items.*.duration_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'items.*.estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'items.*.depends_on' => ['nullable', 'integer', 'min:0'],
            'items.*.checklist' => ['array', 'max:30'],
            'items.*.checklist.*' => ['string', 'max:255'],
        ], ['items.required' => __('Add at least one task.')], [
            'items.*.title' => __('title'), 'items.*.task_type_id' => __('type'),
            'items.*.offset_days_start' => __('start offset'), 'items.*.duration_days' => __('duration'),
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($items, $ownIds): void {
            foreach ($items as $index => $item) {
                if ($item['id'] !== null && ! in_array($item['id'], $ownIds, true)) {
                    $validator->errors()->add("items.{$index}.id", __('This item belongs to another template.'));
                }

                if ($item['depends_on'] !== null && (int) $item['depends_on'] >= $index) {
                    $validator->errors()->add("items.{$index}.depends_on", __('A task can only depend on an earlier task.'));
                }
            }
        });

        $data = $validator->validate();

        return DB::transaction(function () use ($template, $data, $items): TaskTemplate {
            $template ??= new TaskTemplate;
            $template->fill([
                'name' => $data['name'],
                'service_id' => $data['service_id'] ?? null,
                'project_type_id' => $data['project_type_id'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            $template->items()->whereNotIn('id', array_values(array_filter(array_column($items, 'id'))))->delete();
            $saved = [];

            foreach ($items as $index => $item) {
                $attributes = [
                    'title' => $item['title'],
                    'task_type_id' => (int) $item['task_type_id'],
                    'project_phase_id' => $item['project_phase_id'] !== null ? (int) $item['project_phase_id'] : null,
                    'project_role_id' => $item['project_role_id'] !== null ? (int) $item['project_role_id'] : null,
                    'offset_days_start' => (int) $item['offset_days_start'],
                    'duration_days' => (int) $item['duration_days'],
                    'estimated_hours' => $item['estimated_hours'],
                    'checklist' => $item['checklist'] !== [] ? $item['checklist'] : null,
                    'sort_order' => $index + 1,
                    'depends_on_item_id' => $item['depends_on'] !== null ? $saved[(int) $item['depends_on']]->id : null,
                ];

                $saved[$index] = $item['id'] !== null
                    ? tap($template->items()->findOrFail($item['id']), fn ($model) => $model->fill($attributes)->save())
                    : $template->items()->create($attributes);
            }

            return $template;
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{id: int|null, title: string|null, task_type_id: mixed, project_phase_id: mixed, project_role_id: mixed, offset_days_start: mixed, duration_days: mixed, estimated_hours: mixed, depends_on: mixed, checklist: list<string>}
     */
    private function cleanItem(array $item): array
    {
        $blank = fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value;
        $checklist = $item['checklist'] ?? [];

        if (is_string($checklist)) {
            $checklist = preg_split('/\R/', $checklist) ?: [];
        }

        return [
            'id' => is_numeric($item['id'] ?? null) ? (int) $item['id'] : null,
            'title' => is_string($item['title'] ?? null) ? trim($item['title']) : null,
            'task_type_id' => $blank($item['task_type_id'] ?? null),
            'project_phase_id' => $blank($item['project_phase_id'] ?? null),
            'project_role_id' => $blank($item['project_role_id'] ?? null),
            'offset_days_start' => $blank($item['offset_days_start'] ?? null) ?? 0,
            'duration_days' => $blank($item['duration_days'] ?? null) ?? 1,
            'estimated_hours' => $blank($item['estimated_hours'] ?? null),
            'depends_on' => $blank($item['depends_on'] ?? null),
            'checklist' => array_values(array_filter(array_map(fn (mixed $line): string => trim((string) $line), is_array($checklist) ? $checklist : []), fn (string $line): bool => $line !== '')),
        ];
    }
}
