<?php

namespace App\Modules\Foundation\Livewire\Admin\Users;

use App\Models\User;
use App\Modules\Foundation\Actions\CreateUser;
use App\Modules\Foundation\Actions\UpdateUser;
use App\Modules\Foundation\Concerns\SavesFromDetailModal;
use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class Form extends Component
{
    use SavesFromDetailModal;

    public ?User $user = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $phone = '';

    public ?int $branch_id = null;

    public int|string|null $employee_id = null;

    /** @var list<string> */
    public array $roles = [];

    /** @var list<string> */
    public array $permissions = [];

    public string $password = '';

    public string $password_confirmation = '';

    public bool $is_active = true;

    /** "Create user" on an employee profile passes ?employee=CODE (spec H8). */
    #[Url]
    public ?string $employee = null;

    public function mount(?User $user = null): void
    {
        if ($user === null || ! $user->exists) {
            $this->authorize('admin.users.create');

            $this->employee_id = filled($this->employee) ? Employee::query()->where('employee_code', $this->employee)->whereDoesntHave('user')->value('id') : null;
            $this->updatedEmployeeId();

            return;
        }

        $this->authorize('admin.users.update');

        $this->user = $user;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
        $this->branch_id = $user->branch_id;
        $this->employee_id = $user->employee_id;
        $this->is_active = $user->is_active;
        $this->roles = array_values(array_map(strval(...), $user->roles()->pluck('code')->all()));
        $this->permissions = array_values(array_map(strval(...), $user->directPermissions()->pluck('name')->all()));
    }

    /**
     * Picking an employee fills the empty name, email and phone fields (docs/01 §5.3).
     */
    public function updatedEmployeeId(): void
    {
        $employee = filled($this->employee_id) ? Employee::query()->find((int) $this->employee_id) : null;

        if ($employee === null) {
            return;
        }

        $this->name = $this->name !== '' ? $this->name : $employee->full_name;
        $this->email = $this->email !== '' ? $this->email : (string) $employee->official_email;
        $this->phone = $this->phone !== '' ? $this->phone : $employee->phone;
    }

    public function save(CreateUser $createUser, UpdateUser $updateUser): void
    {
        $this->authorize($this->user === null ? 'admin.users.create' : 'admin.users.update');

        $this->branch_id = filled($this->branch_id) ? (int) $this->branch_id : null;
        $this->employee_id = filled($this->employee_id) ? (int) $this->employee_id : null;

        $input = $this->only(['name', 'username', 'email', 'phone', 'branch_id', 'employee_id', 'roles', 'permissions', 'password', 'password_confirmation', 'is_active']);

        $this->user === null
            ? $createUser->handle($input, $this->actor())
            : $updateUser->handle($this->user, $input, $this->actor());

        $this->redirectAfterSave($this->user === null ? __('User created.') : __('User saved.'), 'admin.users.index');
    }

    public function render(): View
    {
        return view('livewire.admin.users.form', [
            'availableRoles' => Role::query()->where('is_active', true)->orderBy('name')->get(['code', 'name', 'description']),
            'canGrantSuperAdmin' => $this->actor()->hasRole(Role::SUPER_ADMIN),
            'permissionGroups' => Permission::query()->orderBy('sort_order')->get()->groupBy('module'),
            'usernameUsed' => $this->user?->last_login_at !== null,
            'employees' => Employee::query()
                ->where(fn ($query) => $query->whereDoesntHave('user')->when($this->user?->employee_id, fn ($query, int $id) => $query->orWhere('id', $id)))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_code']),
        ])
            ->title($this->user === null ? __('New user') : __('Edit user'))
            ->layoutData(['back' => route('admin.users.index'), 'bottomNav' => false]);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
