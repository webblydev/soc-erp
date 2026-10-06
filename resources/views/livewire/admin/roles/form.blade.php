<div>
    @if ($readOnly)
        <x-ui.alert>
            <x-lucide-shield-check />
            <x-ui.alert-title>{{ $name }}</x-ui.alert-title>
            <x-ui.alert-description>{{ __('Super admin has every permission and cannot be edited.') }}</x-ui.alert-description>
        </x-ui.alert>
    @else
        <x-shell.form-screen wire:submit="save"
            :heading="$role ? $role->name : __('New role')"
            :description="__('A role bundles permissions. Everyone holding it gets them straight away.')"
            :cancel-url="route('admin.roles.index')"
            :submit-label="__('Save role')"
            aside-position="start">
            <x-slot:aside>
                <x-shell.form-section :title="__('Details')">
                    <x-ui.field>
                        <x-ui.field-label for="name">{{ __('Name') }} *</x-ui.field-label>
                        <x-ui.input id="name" wire:model="name" class="h-11 text-base md:h-9 md:text-sm" />
                        <x-ui.field-error :messages="$errors->get('name')" />
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="code">{{ __('Code') }} *</x-ui.field-label>
                        <x-ui.input id="code" wire:model="code" autocapitalize="none" class="h-11 font-mono text-base md:h-9 md:text-sm" :disabled="$role?->is_system" />
                        <x-ui.field-description>{{ __('Lowercase letters, numbers and underscores. Used by the system; cannot change on system roles.') }}</x-ui.field-description>
                        <x-ui.field-error :messages="$errors->get('code')" />
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="description">{{ __('Description') }}</x-ui.field-label>
                        <x-ui.textarea id="description" wire:model="description" rows="2" class="text-base md:text-sm" />
                        <x-ui.field-error :messages="$errors->get('description')" />
                    </x-ui.field>

                    <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                        <x-ui.switch id="is_active" wire:model="is_active" :checked="$is_active" />
                        <x-ui.field-label for="is_active">{{ __('Active') }}</x-ui.field-label>
                    </x-ui.field>
                </x-shell.form-section>
            </x-slot:aside>

            <x-shell.form-section :title="__('Permissions')" :description="__('Click a column or row heading to select or clear all of it.')">
                <x-slot:action>
                    <x-ui.badge variant="secondary"><span x-text="$wire.permissions.length">{{ count($permissions) }}</span> {{ __('selected') }}</x-ui.badge>
                </x-slot:action>

                <div class="flex flex-col gap-2">
                    <h2 class="text-base font-semibold md:hidden">{{ __('Permissions') }}</h2>
                    <x-ui.field-error :messages="$errors->get('role')" />

                    {{-- Desktop matrix --}}
                    <div class="hidden flex-col gap-4 md:flex">
                        @foreach ($matrix as $module => $group)
                            @php($moduleNames = array_merge(...array_map('array_values', array_values($group['resources']))))
                            <div class="overflow-hidden rounded-lg border" wire:key="matrix-{{ $module }}">
                                <div class="flex items-center justify-between gap-2 border-b bg-muted/50 px-4 py-2">
                                    <span class="text-sm font-semibold uppercase">{{ $module }}</span>
                                    <span class="text-xs text-muted-foreground" x-data="{ names: @js($moduleNames) }"><span x-text="$wire.permissions.filter(name => names.includes(name)).length">{{ count(array_intersect($moduleNames, $permissions)) }}</span> / {{ count($moduleNames) }}</span>
                                </div>
                                <x-ui.table>
                                    <x-ui.table-header>
                                        <x-ui.table-row>
                                            <x-ui.table-head class="w-1/3">{{ __('Resource') }}</x-ui.table-head>
                                            @foreach ($group['actions'] as $action)
                                                <x-ui.table-head class="text-center">
                                                    <button type="button" wire:click="toggleAction(@js($module), @js($action))" class="rounded px-1 capitalize hover:text-foreground hover:underline">{{ str_replace('_', ' ', $action) }}</button>
                                                </x-ui.table-head>
                                            @endforeach
                                        </x-ui.table-row>
                                    </x-ui.table-header>
                                    <x-ui.table-body>
                                        @foreach ($group['resources'] as $resource => $names)
                                            <x-ui.table-row>
                                                <x-ui.table-cell>
                                                    <button type="button" wire:click="toggleResource(@js($module), @js($resource))" class="rounded px-1 capitalize hover:underline">{{ $resource !== '' ? str_replace('_', ' ', $resource) : $module }}</button>
                                                </x-ui.table-cell>
                                                @foreach ($group['actions'] as $action)
                                                    <x-ui.table-cell class="text-center">
                                                        @isset($names[$action])
                                                            <x-ui.checkbox native wire:model="permissions" value="{{ $names[$action] }}" :aria-label="$names[$action]" />
                                                        @else
                                                            <span class="text-muted-foreground/40" aria-hidden="true">—</span>
                                                        @endisset
                                                    </x-ui.table-cell>
                                                @endforeach
                                            </x-ui.table-row>
                                        @endforeach
                                    </x-ui.table-body>
                                </x-ui.table>
                            </div>
                        @endforeach
                    </div>

                    {{-- Mobile: accordion per module, checkbox rows per action --}}
                    <x-ui.accordion type="multiple" class="md:hidden">
                        @foreach ($matrix as $module => $group)
                            <x-ui.accordion-item value="{{ $module }}">
                                <x-ui.accordion-trigger class="min-h-11 text-base uppercase">{{ $module }}</x-ui.accordion-trigger>
                                <x-ui.accordion-content>
                                    @foreach ($group['resources'] as $resource => $names)
                                        <div class="mb-3">
                                            <button type="button" wire:click="toggleResource(@js($module), @js($resource))" class="min-h-11 text-sm font-semibold">
                                                {{ $resource !== '' ? str_replace('_', ' ', $resource) : $module }}
                                            </button>
                                            @foreach ($names as $action => $permissionName)
                                                <label class="flex min-h-11 items-center gap-3 text-sm">
                                                    <x-ui.checkbox native wire:model="permissions" value="{{ $permissionName }}" />
                                                    {{ str_replace('_', ' ', $action) }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </x-ui.accordion-content>
                            </x-ui.accordion-item>
                        @endforeach
                    </x-ui.accordion>
                </div>
            </x-shell.form-section>
        </x-shell.form-screen>
    @endif
</div>
