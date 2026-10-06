<div>
    <x-shell.list
        :search-placeholder="__('Search roles')"
        :create-url="auth()->user()->can('admin.roles.create') ? route('admin.roles.create') : null"
        :create-label="__('New role')"
        exportable
        :has-more="$this->hasMoreRows"
    >
        <x-slot:desktop>
            <x-shell.bulk-bar exportable :deletable="auth()->user()->can('admin.roles.delete')" />

            <x-ui.table variant="bordered">
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-shell.select-all :ids="$rows->pluck('id')" />
                        <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Code') }}</x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="users" :label="__('Users')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $role)
                        <x-ui.table-row wire:key="role-{{ $role->id }}">
                            <x-shell.select-row :id="$role->id" :label="$role->name" />
                            <x-shell.row-menu>
                                @can('admin.roles.update')
                                    @if ($role->code === \App\Modules\Foundation\Models\Role::SUPER_ADMIN)
                                        <x-shell.row-menu-item icon="eye" :href="route('admin.roles.edit', $role)">{{ __('View') }}</x-shell.row-menu-item>
                                    @else
                                        <x-shell.row-menu-item icon="pencil" :href="route('admin.roles.edit', $role)">{{ __('Edit') }}</x-shell.row-menu-item>
                                    @endif
                                @endcan
                                @if (! $role->is_system)
                                    @can('admin.roles.delete')
                                        <x-shell.row-menu-item icon="trash-2" destructive wire:click="confirmDelete({{ $role->id }})">{{ __('Delete') }}</x-shell.row-menu-item>
                                    @endcan
                                @endif
                            </x-shell.row-menu>
                            <x-ui.table-cell class="font-medium">
                                <a href="{{ route('admin.roles.edit', $role) }}" wire:navigate class="hover:underline">{{ $role->name }}</a>
                                @if ($role->is_system)
                                    <x-ui.badge tone="neutral" class="ms-2">{{ __('System') }}</x-ui.badge>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell class="font-mono text-sm">{{ $role->code }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $role->users_count }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.badge :tone="$role->is_active ? 'success' : 'neutral'">{{ $role->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="6" class="py-10 text-center text-muted-foreground">{{ __('No roles found.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
            <div class="mt-4">{{ $rows->links() }}</div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $role)
                <x-ui.item variant="outline" class="min-h-16 gap-2 py-2 pe-1" wire:key="m-role-{{ $role->id }}">
                    <a href="{{ route('admin.roles.edit', $role) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3 rounded-md active:bg-accent">
                        <x-ui.item-content>
                            <x-ui.item-title class="text-base">{{ $role->name }}</x-ui.item-title>
                            <x-ui.item-description class="text-sm">{{ trans_choice(':count user|:count users', $role->users_count, ['count' => $role->users_count]) }}</x-ui.item-description>
                        </x-ui.item-content>
                        <x-ui.badge class="text-sm" :tone="$role->is_active ? 'success' : 'neutral'">{{ $role->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                    </a>
                    @if (! $role->is_system && auth()->user()->can('admin.roles.delete'))
                        <x-ui.button variant="ghost" size="icon" class="size-11" wire:click="confirmDelete({{ $role->id }})" :aria-label="__('Delete')"><x-lucide-trash-2 class="size-5" /></x-ui.button>
                    @else
                        <x-lucide-chevron-right class="size-5 text-muted-foreground" />
                    @endif
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No roles found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>

    <x-shell.sheet id="role-delete" :title="__('Delete role?')" :description="$deletingRole ? __('“:name” will be removed permanently.', ['name' => $deletingRole->name]) : __('The role will be removed permanently.')">
        <x-slot:footer>
            <x-ui.button variant="outline" type="button" x-on:click="$dispatch('close-sheet-role-delete')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button variant="destructive" wire:click="delete">{{ __('Delete') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
