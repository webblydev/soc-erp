<?php

namespace App\Modules\Foundation\Livewire\Admin\Roles;

use App\Modules\Foundation\Actions\SaveRole;
use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class Form extends Component
{
    public ?Role $role = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public bool $is_active = true;

    /** @var list<string> */
    public array $permissions = [];

    public bool $readOnly = false;

    public function mount(?Role $role = null): void
    {
        if ($role === null || ! $role->exists) {
            $this->authorize('admin.roles.create');

            return;
        }

        $this->authorize('admin.roles.update');

        $this->role = $role;
        $this->readOnly = $role->code === Role::SUPER_ADMIN;
        $this->name = $role->name;
        $this->code = $role->code;
        $this->description = (string) $role->description;
        $this->is_active = (bool) ($role->is_active ?? true);
        $this->permissions = array_values(array_map(strval(...), $role->permissions()->pluck('name')->all()));
    }

    public function toggleResource(string $module, string $resource): void
    {
        $this->toggle(Permission::query()->where('module', $module)->where('resource', $resource)->orderBy('sort_order')->orderBy('id')->pluck('name'));
    }

    public function toggleAction(string $module, string $action): void
    {
        $this->toggle(Permission::query()->where('module', $module)->where('action', $action)->orderBy('sort_order')->orderBy('id')->pluck('name'));
    }

    public function save(SaveRole $saveRole): void
    {
        $this->authorize($this->role === null ? 'admin.roles.create' : 'admin.roles.update');

        $saveRole->handle($this->only(['name', 'code', 'description', 'is_active', 'permissions']), $this->role);

        session()->flash('success', __('Role saved.'));

        $this->redirectRoute('admin.roles.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.roles.form', ['matrix' => $this->matrix()])
            ->title($this->role === null ? __('New role') : $this->role->name)
            ->layoutData(['back' => route('admin.roles.index'), 'bottomNav' => false]);
    }

    /**
     * Modules → their action columns and resource rows (action → permission name).
     *
     * @return array<string, array{actions: list<string>, resources: array<string, array<string, string>>}>
     */
    private function matrix(): array
    {
        return Permission::query()->orderBy('sort_order')->orderBy('id')->get()
            ->groupBy('module')
            ->map(fn (Collection $permissions): array => [
                'actions' => array_values($permissions->pluck('action')->unique()->all()),
                'resources' => $permissions->groupBy('resource')
                    ->map(fn (Collection $rows): array => $rows->pluck('name', 'action')->all())
                    ->all(),
            ])
            ->all();
    }

    /**
     * Select all of the given names, or clear them when they are already all selected.
     *
     * @param  Collection<int, mixed>  $names
     */
    private function toggle(Collection $names): void
    {
        $names = $names->map(fn (mixed $name): string => (string) $name)->all();

        $this->permissions = array_diff($names, $this->permissions) === []
            ? array_values(array_diff($this->permissions, $names))
            : array_values(array_unique([...$this->permissions, ...$names]));
    }
}
