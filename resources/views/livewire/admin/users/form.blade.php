<x-shell.form-page wire:submit="save" :cancel-url="route('admin.users.index')" :submit-label="$user ? __('Save changes') : __('Create user')">
    @error('user')
        <x-ui.alert variant="destructive"><x-ui.alert-description>{{ $message }}</x-ui.alert-description></x-ui.alert>
    @enderror

    <x-ui.field>
        <x-ui.field-label for="name">{{ __('Name') }} *</x-ui.field-label>
        <x-ui.input id="name" wire:model="name" autocomplete="name" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('name') ? 'true' : null" />
        <x-ui.field-error :messages="$errors->get('name')" />
    </x-ui.field>

    <x-ui.field>
        <x-ui.field-label for="username">{{ __('Username') }} *</x-ui.field-label>
        <x-ui.input id="username" wire:model="username" autocapitalize="none" autocomplete="off" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('username') ? 'true' : null" />
        @if ($usernameUsed)
            <x-ui.field-description>{{ __('This user has already signed in. Changing the username changes how they log in, and the change is recorded in the audit log.') }}</x-ui.field-description>
        @endif
        <x-ui.field-error :messages="$errors->get('username')" />
    </x-ui.field>

    <x-ui.field>
        <x-ui.field-label for="email">{{ __('Email') }}</x-ui.field-label>
        <x-ui.input id="email" type="email" inputmode="email" wire:model="email" autocapitalize="none" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('email') ? 'true' : null" />
        <x-ui.field-error :messages="$errors->get('email')" />
    </x-ui.field>

    <x-ui.field>
        <x-ui.field-label for="phone">{{ __('Phone') }}</x-ui.field-label>
        <x-ui.input id="phone" type="tel" inputmode="tel" wire:model="phone" placeholder="01XXXXXXXXX" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('phone') ? 'true' : null" />
        <x-ui.field-error :messages="$errors->get('phone')" />
    </x-ui.field>

    <x-ui.field>
        <x-ui.field-label for="branch_id">{{ __('Branch') }}</x-ui.field-label>
        <x-ui.select native id="branch_id" wire:model="branch_id" class="h-11 text-base md:h-9 md:text-sm">
            <option value="">{{ __('No branch') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.field-error :messages="$errors->get('branch_id')" />
    </x-ui.field>

    <x-ui.field-set>
        <x-ui.field-legend>{{ __('Roles') }} *</x-ui.field-legend>
        <div class="flex flex-col gap-2">
            @foreach ($availableRoles as $role)
                @php($locked = $role->code === \App\Modules\Foundation\Models\Role::SUPER_ADMIN && ! $canGrantSuperAdmin)
                <label class="flex min-h-11 items-center gap-3 rounded-md border px-3 py-2 has-[:checked]:border-primary {{ $locked ? 'opacity-50' : '' }}">
                    <x-ui.checkbox native wire:model="roles" value="{{ $role->code }}" :disabled="$locked" />
                    <span class="flex flex-col">
                        <span class="text-sm font-medium">{{ $role->name }}</span>
                        @if ($role->description)
                            <span class="text-sm text-muted-foreground">{{ $role->description }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
        <x-ui.field-error :messages="$errors->get('roles')" />
    </x-ui.field-set>

    <x-ui.collapsible>
        <x-ui.collapsible-trigger class="flex min-h-11 w-full items-center justify-between text-sm font-medium">
            {{ __('Extra permissions (:count)', ['count' => count($permissions)]) }}
            <x-lucide-chevron-down class="size-4" />
        </x-ui.collapsible-trigger>
        <x-ui.collapsible-content>
            <div class="flex flex-col gap-4 pt-2">
                @foreach ($permissionGroups as $module => $modulePermissions)
                    <div>
                        <p class="mb-1 text-sm font-semibold uppercase text-muted-foreground">{{ $module }}</p>
                        <div class="grid gap-1 md:grid-cols-2">
                            @foreach ($modulePermissions as $permission)
                                <label class="flex min-h-11 items-center gap-3 text-sm md:min-h-8">
                                    <x-ui.checkbox native wire:model="permissions" value="{{ $permission->name }}" />
                                    {{ $permission->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.collapsible-content>
    </x-ui.collapsible>

    <x-ui.field>
        <x-ui.field-label for="password">{{ __('Password') }} {{ $user ? '' : '*' }}</x-ui.field-label>
        <x-ui.input id="password" type="password" wire:model="password" autocomplete="new-password" class="h-11 text-base md:h-9 md:text-sm" :aria-invalid="$errors->has('password') ? 'true' : null" />
        <x-ui.field-description>
            {{ $user ? __('Leave blank to keep the current password. A new password must be changed at next login.') : __('The user must change it at first login.') }}
        </x-ui.field-description>
        <x-ui.field-error :messages="$errors->get('password')" />
    </x-ui.field>

    <x-ui.field>
        <x-ui.field-label for="password_confirmation">{{ __('Confirm password') }}</x-ui.field-label>
        <x-ui.input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" class="h-11 text-base md:h-9 md:text-sm" />
    </x-ui.field>

    <x-ui.field orientation="horizontal" class="min-h-11 items-center">
        <x-ui.switch id="is_active" wire:model="is_active" :checked="$is_active" />
        <x-ui.field-label for="is_active">{{ __('Active') }}</x-ui.field-label>
    </x-ui.field>
</x-shell.form-page>
