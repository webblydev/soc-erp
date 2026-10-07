<?php

namespace App\Modules\Projects\Livewire\Templates;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Foundation\Concerns\SavesFromDetailModal;
use App\Modules\Projects\Actions\SaveTaskTemplate;
use App\Modules\Projects\Models\TaskTemplate;
use App\Modules\Projects\Models\TaskType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * New / edit task template with its items and a date preview (docs/04 §5.8, spec P17).
 */
class Form extends Component
{
    use SavesFromDetailModal;

    public ?TaskTemplate $template = null;

    public string $name = '';

    public int|string|null $service_id = null;

    public int|string|null $project_type_id = null;

    public bool $is_active = true;

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public string $previewStart = '';

    public function mount(?TaskTemplate $template = null): void
    {
        $this->authorize('projects.task_templates.manage');
        $this->previewStart = today()->toDateString();

        if ($template === null || ! $template->exists) {
            $this->addItem();

            return;
        }

        $this->template = $template;
        $this->name = $template->name;
        $this->service_id = $template->service_id;
        $this->project_type_id = $template->project_type_id;
        $this->is_active = $template->is_active;

        $items = $template->items()->get();
        $positions = $items->pluck('id')->flip();

        $this->items = $items->map(fn ($item): array => [
            'id' => $item->id,
            'title' => $item->title,
            'task_type_id' => $item->task_type_id,
            'project_phase_id' => $item->project_phase_id,
            'project_role_id' => $item->project_role_id,
            'offset_days_start' => (string) $item->offset_days_start,
            'duration_days' => (string) $item->duration_days,
            'estimated_hours' => $item->estimated_hours !== null ? rtrim(rtrim($item->estimated_hours, '0'), '.') : '',
            'depends_on' => $item->depends_on_item_id !== null ? (string) $positions[$item->depends_on_item_id] : '',
            'checklist' => implode("\n", $item->checklist ?? []),
        ])->values()->all();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'id' => null, 'title' => '', 'task_type_id' => TaskType::query()->where('code', TaskType::OTHER)->value('id'), 'project_phase_id' => null,
            'project_role_id' => null, 'offset_days_start' => '0', 'duration_days' => '1', 'estimated_hours' => '', 'depends_on' => '', 'checklist' => '',
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values(array_map(function (array $item) use ($index): array {
            if ($item['depends_on'] !== '' && (int) $item['depends_on'] === $index) {
                $item['depends_on'] = '';
            } elseif ($item['depends_on'] !== '' && (int) $item['depends_on'] > $index) {
                $item['depends_on'] = (string) ((int) $item['depends_on'] - 1);
            }

            return $item;
        }, $this->items));
    }

    public function save(SaveTaskTemplate $saveTaskTemplate): void
    {
        $this->authorize('projects.task_templates.manage');

        $template = $saveTaskTemplate->handle($this->actor(), $this->only(['name', 'service_id', 'project_type_id', 'is_active', 'items']), $this->template);

        $this->redirectAfterSave(__('Template saved.'), 'projects.templates.edit', $template);
    }

    /**
     * Start and due dates of each item for the preview start date.
     *
     * @return list<array{start: string, due: string}|null>
     */
    private function preview(): array
    {
        $start = strtotime($this->previewStart) !== false ? Carbon::parse($this->previewStart) : today();

        return array_map(function (array $item) use ($start): ?array {
            if (! is_numeric($item['offset_days_start']) || ! is_numeric($item['duration_days'])) {
                return null;
            }

            $itemStart = $start->copy()->addDays((int) $item['offset_days_start']);

            return ['start' => $itemStart->format('d M'), 'due' => $itemStart->copy()->addDays((int) $item['duration_days'])->format('d M')];
        }, $this->items);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.projects.templates.form', [
            'preview' => $this->preview(),
            'services' => Service::query()->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->template?->service_id))->orderBy('name')->get(['id', 'name']),
        ])
            ->title($this->template === null ? __('New template') : __('Edit template'))
            ->layoutData(['back' => route('projects.templates.index'), 'bottomNav' => false]);
    }
}
