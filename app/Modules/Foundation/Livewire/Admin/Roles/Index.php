<?php

namespace App\Modules\Foundation\Livewire\Admin\Roles;

use App\Modules\Foundation\Actions\DeleteRole;
use App\Modules\Foundation\Models\Role;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Roles')]
class Index extends Component
{
    use WithListing;

    public ?int $deletingRoleId = null;

    public function mount(): void
    {
        $this->authorize('admin.roles.view');
    }

    public function confirmDelete(int $roleId): void
    {
        $this->authorize('admin.roles.delete');

        $this->deletingRoleId = $roleId;
        $this->dispatch('open-sheet-role-delete');
    }

    public function delete(DeleteRole $deleteRole): void
    {
        $this->authorize('admin.roles.delete');

        $role = Role::query()->findOrFail($this->deletingRoleId);

        try {
            $deleteRole->handle($role);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->deletingRoleId = null;
        $this->dispatch('close-sheet-role-delete');
        $this->dispatch('toast', type: 'success', description: __('Role deleted.'));
    }

    /**
     * @return Builder<Role>
     */
    protected function listingQuery(): Builder
    {
        return Role::query()->withCount('users')->orderBy('name');
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    protected function sortColumns(): array
    {
        return ['name' => 'name', 'users' => 'users_count'];
    }

    public function render(): View
    {
        return view('livewire.admin.roles.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'deletingRole' => $this->deletingRoleId !== null ? Role::query()->find($this->deletingRoleId) : null,
        ]);
    }
}
