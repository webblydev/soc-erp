<?php

namespace App\Modules\Foundation\Livewire\Admin;

use App\Modules\Foundation\Actions\UpdateSettings;
use App\Modules\Foundation\Models\Setting;
use App\Support\Facades\Lookup;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Settings')]
class Settings extends Component
{
    #[Url(except: 'general')]
    public string $group = 'general';

    /** @var array<string, array<string, mixed>> */
    public array $values = [];

    public function mount(): void
    {
        $this->authorize('admin.settings.view');

        foreach (Setting::query()->orderBy('id')->get() as $setting) {
            $this->values[$setting->group][$setting->key] = $setting->type === 'json'
                ? json_encode($setting->value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : $setting->value;
        }

        if (! array_key_exists($this->group, $this->values)) {
            $this->group = (string) array_key_first($this->values);
        }
    }

    public function save(UpdateSettings $updateSettings): void
    {
        $this->authorize('admin.settings.update');

        abort_unless(Setting::query()->where('group', $this->group)->exists(), 404);

        try {
            $updateSettings->handle($this->group, $this->values[$this->group] ?? []);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["values.{$this->group}.{$key}" => $messages])->all(),
            );
        }

        $this->dispatch('toast', type: 'success', description: __('Settings saved.'));
    }

    public function render(): View
    {
        $settings = Setting::query()->orderBy('id')->get();

        return view('livewire.admin.settings', [
            'groups' => $settings->pluck('group')->unique()->values(),
            'fields' => $settings->where('group', $this->group)->values(),
            'lookupOptions' => fn (string $table, mixed $current) => Lookup::options($table, is_numeric($current) ? (int) $current : null),
            'readOnly' => ! auth()->user()->can('admin.settings.update'),
        ]);
    }
}
