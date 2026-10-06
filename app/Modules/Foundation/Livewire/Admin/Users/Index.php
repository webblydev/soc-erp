<?php

namespace App\Modules\Foundation\Livewire\Admin\Users;

use App\Models\User;
use App\Modules\Foundation\Actions\SetUserActive;
use App\Modules\Foundation\Actions\StartImpersonation;
use App\Modules\Foundation\Models\Role;
use App\Support\Exports\ListingExport;
use App\Support\Facades\Lookup;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Users')]
class Index extends Component
{
    use WithListing;

    public ?int $actionUserId = null;

    public function mount(): void
    {
        $this->authorize('admin.users.view');
    }

    public function openActions(int $userId): void
    {
        $this->authorize('admin.users.view');

        $this->actionUserId = $userId;
        $this->dispatch('open-sheet-user-actions');
    }

    public function toggleActive(int $userId, SetUserActive $setUserActive): void
    {
        $this->authorize('admin.users.deactivate');

        $user = User::query()->findOrFail($userId);

        try {
            $setUserActive->handle($user, ! $user->is_active, $this->actor());
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->dispatch('close-sheet-user-actions');
        $this->dispatch('toast', type: 'success', description: $user->is_active ? __('User activated.') : __('User deactivated.'));
    }

    public function impersonate(int $userId, StartImpersonation $startImpersonation): void
    {
        $this->authorize('admin.users.impersonate');

        try {
            $startImpersonation->handle($this->actor(), User::query()->findOrFail($userId));
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->redirect(route('dashboard'));
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('admin.users.view');

        return ListingExport::download('users', $this->filteredQuery(), [
            'Name' => 'name',
            'Username' => 'username',
            'Email' => 'email',
            'Phone' => 'phone',
            'Roles' => fn (User $user): string => $user->roles->pluck('name')->implode(', '),
            'Branch' => 'branch.name',
            'Active' => fn (User $user): string => $user->is_active ? 'Yes' : 'No',
            'Last login' => fn (User $user): ?string => $user->last_login_at?->format('d-M-Y H:i'),
        ]);
    }

    /**
     * @return Builder<User>
     */
    protected function listingQuery(): Builder
    {
        return User::query()->with(['roles:id,code,name', 'branch:id,name'])->orderBy('name');
    }

    protected function searchColumns(): array
    {
        return ['users.name', 'users.username', 'users.email', 'users.phone'];
    }

    protected function sortColumns(): array
    {
        return ['name' => 'users.name', 'username' => 'users.username', 'last_login_at' => 'users.last_login_at'];
    }

    /**
     * @param  Builder<*>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        if (filled($this->filters['role'] ?? null)) {
            $query->whereHas('roles', fn (Builder $roles) => $roles->where('code', $this->filters['role']));
        }

        if (filled($this->filters['branch'] ?? null)) {
            $query->where('branch_id', (int) $this->filters['branch']);
        }

        if (($this->filters['active'] ?? '') !== '') {
            $query->where('is_active', $this->filters['active'] === '1');
        }
    }

    public function render(): View
    {
        return view('livewire.admin.users.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
            'roles' => Role::query()->orderBy('name')->get(['code', 'name']),
            'branches' => Lookup::options('branches'),
            'actionUser' => $this->actionUserId !== null ? User::query()->find($this->actionUserId) : null,
        ]);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
