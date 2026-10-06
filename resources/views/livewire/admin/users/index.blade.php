<div>
    <x-shell.list
        :search-placeholder="__('Search name, username, email or phone')"
        :create-url="auth()->user()->can('admin.users.create') ? route('admin.users.create') : null"
        :create-label="__('New user')"
        exportable
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-role">{{ __('Role') }}</x-ui.field-label>
                <x-ui.select native id="filter-role" wire:model.live="filters.role" class="h-11 md:h-9">
                    <option value="">{{ __('All roles') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->code }}">{{ $role->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-branch">{{ __('Branch') }}</x-ui.field-label>
                <x-lookup-select table="branches" :placeholder="__('All branches')" id="filter-branch" wire:model.live="filters.branch" class="h-11 md:h-9" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-active">{{ __('Status') }}</x-ui.field-label>
                <x-ui.select native id="filter-active" wire:model.live="filters.active" class="h-11 md:h-9">
                    <option value="">{{ __('All') }}</option>
                    <option value="1">{{ __('Active') }}</option>
                    <option value="0">{{ __('Inactive') }}</option>
                </x-ui.select>
            </x-ui.field>
        </x-slot:filters>

        <x-slot:desktop>
            <x-shell.bulk-bar exportable :deletable="auth()->user()->can('admin.users.delete')" />

            <x-ui.table variant="bordered">
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-shell.select-all :ids="$rows->pluck('id')" />
                        <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="username" :label="__('Username')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Email') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Phone') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Roles') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Branch') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Active') }}</x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="last_login_at" :label="__('Last login')" :$sort :$direction /></x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $user)
                        <x-ui.table-row wire:key="user-{{ $user->id }}-{{ (int) $user->is_active }}">
                            <x-shell.select-row :id="$user->id" :label="$user->name" />
                            <x-shell.row-menu>
                                @can('admin.users.update')
                                    <x-shell.row-menu-item icon="pencil" :href="route('admin.users.edit', $user)">{{ __('Edit') }}</x-shell.row-menu-item>
                                @endcan
                                @can('admin.users.deactivate')
                                    <x-shell.row-menu-item :icon="$user->is_active ? 'power-off' : 'power'" wire:click="toggleActive({{ $user->id }})">{{ $user->is_active ? __('Deactivate') : __('Activate') }}</x-shell.row-menu-item>
                                @endcan
                                @if (auth()->user()->hasRole(\App\Modules\Foundation\Models\Role::SUPER_ADMIN) && $user->is_active && ! $user->is(auth()->user()))
                                    <x-shell.row-menu-item icon="log-in" wire:click="impersonate({{ $user->id }})">{{ __('Sign in as') }}</x-shell.row-menu-item>
                                @endif
                                @can('admin.users.delete')
                                    <x-shell.row-menu-item icon="trash-2" destructive wire:click="deleteRecord({{ $user->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $user->name]) }}">{{ __('Delete') }}</x-shell.row-menu-item>
                                @endcan
                            </x-shell.row-menu>
                            <x-ui.table-cell class="font-medium">
                                <a href="{{ route('admin.users.edit', $user) }}" wire:navigate class="hover:underline">{{ $user->name }}</a>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->username }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->email }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->phone }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($user->roles as $role)
                                        <x-ui.badge variant="secondary">{{ $role->name }}</x-ui.badge>
                                    @endforeach
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->branch?->name }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.badge :tone="$user->is_active ? 'success' : 'neutral'">{{ $user->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $user->last_login_at?->format('d-M-Y H:i') ?? '—' }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="10" class="py-10 text-center text-muted-foreground">{{ __('No users found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                <x-ui.select native wire:model.live="perPage" class="w-36" :aria-label="__('Rows per page')">
                    @foreach (static::PER_PAGE_OPTIONS as $option)
                        <option value="{{ $option }}">{{ __(':count per page', ['count' => $option]) }}</option>
                    @endforeach
                </x-ui.select>
                {{ $rows->links() }}
            </div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $user)
                <x-ui.item variant="outline" class="min-h-16 gap-2 py-2 pe-1" wire:key="m-user-{{ $user->id }}">
                    <a href="{{ route('admin.users.edit', $user) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3 rounded-md active:bg-accent">
                        <x-ui.item-content class="min-w-0">
                            <x-ui.item-title class="flex items-center gap-2 text-base">
                                <span class="truncate">{{ $user->name }}</span>
                                <x-ui.badge :tone="$user->is_active ? 'success' : 'neutral'" class="text-sm">{{ $user->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                            </x-ui.item-title>
                            <x-ui.item-description class="text-sm">{{ '@'.$user->username }}</x-ui.item-description>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ($user->roles as $role)
                                    <x-ui.badge variant="secondary" class="text-sm">{{ $role->name }}</x-ui.badge>
                                @endforeach
                            </div>
                        </x-ui.item-content>
                    </a>
                    <x-ui.button variant="ghost" size="icon" class="size-11" wire:click="openActions({{ $user->id }})" :aria-label="__('Actions')">
                        <x-lucide-ellipsis-vertical class="size-5" />
                    </x-ui.button>
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No users found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>

    <x-shell.sheet id="user-actions" :title="$actionUser?->name" :description="$actionUser ? '@'.$actionUser->username : null">
        @if ($actionUser)
            <div class="flex flex-col gap-2 pb-4">
                @can('admin.users.update')
                    <x-ui.button variant="outline" class="h-11 justify-start" :href="route('admin.users.edit', $actionUser)" wire:navigate>
                        <x-lucide-pencil /> {{ __('Edit') }}
                    </x-ui.button>
                @endcan
                @can('admin.users.deactivate')
                    <x-ui.button variant="outline" class="h-11 justify-start" wire:click="toggleActive({{ $actionUser->id }})">
                        <x-lucide-power /> {{ $actionUser->is_active ? __('Deactivate') : __('Activate') }}
                    </x-ui.button>
                @endcan
                @if (auth()->user()->hasRole(\App\Modules\Foundation\Models\Role::SUPER_ADMIN) && $actionUser->is_active && ! $actionUser->is(auth()->user()))
                    <x-ui.button variant="outline" class="h-11 justify-start" wire:click="impersonate({{ $actionUser->id }})">
                        <x-lucide-log-in /> {{ __('Sign in as') }}
                    </x-ui.button>
                @endif
            </div>
        @endif
    </x-shell.sheet>
</div>
