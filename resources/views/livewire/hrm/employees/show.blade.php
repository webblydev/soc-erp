@php
    $user = auth()->user();
    $linked = $employee->user;
    $exited = $employee->status->is_exit;
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $date = fn ($value) => $value?->format('d-M-Y') ?? '—';
@endphp

<x-slot:actions>
    <x-ui.button variant="ghost" size="icon" class="size-11" x-on:click="$dispatch('open-sheet-employee-actions')" :aria-label="__('Employee actions')">
        <x-lucide-ellipsis-vertical class="size-5" />
    </x-ui.button>
</x-slot:actions>

<div class="flex flex-col gap-6 pb-24 md:pb-0">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 items-center gap-4">
            <x-employee-avatar :employee="$employee" class="size-16 text-lg" />
            <div class="flex min-w-0 flex-col gap-1">
                <span class="font-mono text-sm text-muted-foreground">{{ $employee->employee_code }}</span>
                <h1 class="truncate text-xl font-semibold tracking-tight md:text-2xl">{{ $employee->full_name }}</h1>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <x-ui.badge :tone="$employee->status->color ?? 'neutral'" class="text-sm">{{ $employee->status->name }}</x-ui.badge>
                    <span class="text-muted-foreground">{{ $employee->designation->name }} · {{ $employee->department->name }}</span>
                </div>
            </div>
        </div>
        <div class="hidden flex-wrap justify-end gap-2 md:flex">
            @can('update', $employee)
                <x-ui.button size="sm" variant="outline" :href="route('hrm.employees.edit', $employee)" wire:navigate><x-lucide-pencil /> {{ __('Edit') }}</x-ui.button>
            @endcan
            @can('manageHistory', $employee)
                <x-ui.button size="sm" variant="outline" x-on:click="$dispatch('open-sheet-employment-event')"><x-lucide-history /> {{ __('Record event') }}</x-ui.button>
            @endcan
            @can('deactivate', $employee)
                @if ($exited)
                    <x-ui.button size="sm" variant="outline" x-on:click="$dispatch('open-sheet-rejoin')"><x-lucide-undo-2 /> {{ __('Rejoin') }}</x-ui.button>
                @elseif (Route::has('hrm.employees.exit'))
                    <x-ui.button size="sm" variant="outline" class="text-destructive" :href="route('hrm.employees.exit', $employee)" wire:navigate><x-lucide-log-out /> {{ __('Exit') }}</x-ui.button>
                @endif
            @endcan
        </div>
    </div>

    @if ($linked)
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <x-lucide-key-round class="size-4 text-muted-foreground" />
            <span class="text-muted-foreground">{{ __('Login') }}</span>
            @can('admin.users.view')
                <a href="{{ route('admin.users.edit', $linked) }}" wire:navigate class="font-medium hover:underline">{{ $linked->username }}</a>
            @else
                <span class="font-medium">{{ $linked->username }}</span>
            @endcan
            @unless ($linked->is_active)
                <x-ui.badge tone="neutral" class="text-sm">{{ __('Inactive') }}</x-ui.badge>
            @endunless
            @can('admin.users.update')
                <x-ui.button size="sm" variant="ghost" class="h-11 md:h-8" wire:click="unlinkUser" wire:confirm="{{ __('Unlink this login from the employee?') }}">{{ __('Unlink') }}</x-ui.button>
            @endcan
        </div>
    @elseif ($user->can('admin.users.create') || $user->can('admin.users.update'))
        <x-ui.alert>
            <x-lucide-key-round />
            <x-ui.alert-title>{{ __('No login yet') }}</x-ui.alert-title>
            <x-ui.alert-description class="flex flex-wrap gap-2">
                <span class="w-full">{{ __('Create a login for this employee or link an existing one.') }}</span>
                @can('admin.users.create')
                    <x-ui.button size="sm" class="h-11 md:h-8" :href="route('admin.users.create', ['employee' => $employee->employee_code])" wire:navigate><x-lucide-user-plus /> {{ __('Create user') }}</x-ui.button>
                @endcan
                @can('admin.users.update')
                    <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" x-on:click="$dispatch('open-sheet-link-user')"><x-lucide-link /> {{ __('Link user') }}</x-ui.button>
                @endcan
            </x-ui.alert-description>
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Joined') }}</span>
            <span class="text-sm font-medium tabular-nums">{{ $date($employee->joining_date) }}</span>
            <span class="text-sm text-muted-foreground">{{ $employee->joining_date->diffForHumans(($employee->exit_date ?? now()), \Carbon\CarbonInterface::DIFF_ABSOLUTE, parts: 2) }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Type') }}</span>
            <span class="text-sm font-medium">{{ $employee->type->name }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Manager') }}</span>
            @if ($employee->manager)
                <a data-detail-modal href="{{ route('hrm.employees.show', $employee->manager) }}" wire:navigate class="truncate text-sm font-medium hover:underline">{{ $employee->manager->full_name }}</a>
            @else
                <span class="text-sm font-medium">—</span>
            @endif
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Phone') }}</span>
            <a href="tel:{{ $employee->phone }}" class="text-sm font-medium tabular-nums hover:underline">{{ $employee->phone }}</a>
        </x-ui.card>
    </div>

    <div class="flex min-w-0 flex-col gap-4">
        <div class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
            <x-ui.segmented-control name="employee-tab" wire:model.live="tab" :value="$tab" class="h-11 md:h-9"
                :options="collect($tabs)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->all()" />
        </div>

        @if ($tab === 'overview')
            <x-ui.card class="p-4 md:p-6">
                <h2 class="text-base font-semibold">{{ __('Job') }}</h2>
                <x-ui.description-list>
                    <x-ui.description-item :term="__('Department')">{{ $employee->department->name }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Designation')">{{ $employee->designation->name }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Type')">{{ $employee->type->name }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Branch')">{{ $employee->branch?->name ?? '—' }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Joining date')">{{ $date($employee->joining_date) }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Confirmation date')">{{ $date($employee->confirmation_date) }}</x-ui.description-item>
                    @if ($exited)
                        <x-ui.description-item :term="__('Exit date')">{{ $date($employee->exit_date) }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Exit reason')">{{ $employee->exitReason?->name ?? '—' }}</x-ui.description-item>
                    @endif
                </x-ui.description-list>

                <h2 class="mt-4 text-base font-semibold">{{ __('Contact') }}</h2>
                <x-ui.description-list>
                    <x-ui.description-item :term="__('Phone')"><a href="tel:{{ $employee->phone }}" class="tabular-nums hover:underline">{{ $employee->phone }}</a></x-ui.description-item>
                    <x-ui.description-item :term="__('Official email')">{{ $employee->official_email ?? '—' }}</x-ui.description-item>
                    @if ($canViewFull)
                        <x-ui.description-item :term="__('Personal email')">{{ $employee->personal_email ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Present address')">{{ $employee->present_address ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Permanent address')">{{ $employee->permanent_address ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Emergency contact')">{{ collect([$employee->emergency_contact_name, $employee->emergency_contact_relation, $employee->emergency_contact_phone])->filter()->implode(' · ') ?: '—' }}</x-ui.description-item>
                    @endif
                </x-ui.description-list>

                @if ($canViewFull)
                    <h2 class="mt-4 text-base font-semibold">{{ __('Personal') }}</h2>
                    <x-ui.description-list>
                        <x-ui.description-item :term="__('Father’s name')">{{ $employee->father_name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Mother’s name')">{{ $employee->mother_name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Gender')">{{ $employee->gender?->name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Date of birth')">{{ $date($employee->date_of_birth) }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Marital status')">{{ $employee->maritalStatus?->name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Blood group')">{{ $employee->bloodGroup?->name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('NID')"><span class="tabular-nums">{{ $employee->nid_number ?? '—' }}</span></x-ui.description-item>
                        <x-ui.description-item :term="__('TIN')"><span class="tabular-nums">{{ $employee->tin ?? '—' }}</span></x-ui.description-item>
                        <x-ui.description-item :term="__('Reference')">{{ $employee->reference ?? '—' }}</x-ui.description-item>
                    </x-ui.description-list>
                @endif

                <h2 class="mt-4 text-base font-semibold">{{ __('Direct reports') }}</h2>
                <x-ui.item-group class="gap-2">
                    @forelse ($employee->reports as $report)
                        <x-ui.item variant="outline" class="min-h-14 active:bg-accent" data-detail-modal :href="route('hrm.employees.show', $report)" wire:navigate wire:key="report-{{ $report->id }}">
                            <x-employee-avatar :employee="$report" class="size-8" />
                            <x-ui.item-content class="min-w-0">
                                <x-ui.item-title class="text-sm"><span class="truncate">{{ $report->full_name }}</span></x-ui.item-title>
                                <x-ui.item-description class="text-sm">{{ $report->designation->name }}</x-ui.item-description>
                            </x-ui.item-content>
                            <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                        </x-ui.item>
                    @empty
                        <p class="text-sm text-muted-foreground">{{ __('No direct reports.') }}</p>
                    @endforelse
                </x-ui.item-group>
            </x-ui.card>
        @elseif ($tab === 'documents')
            <livewire:hrm.employees.documents :employee="$employee" :key="'documents-'.$employee->id" />
        @elseif ($tab === 'events')
            @can('manageHistory', $employee)
                <x-ui.button class="h-11 self-start md:h-9" x-on:click="$dispatch('open-sheet-employment-event')"><x-lucide-plus /> {{ __('Record event') }}</x-ui.button>
            @endcan
            <x-ui.item-group class="gap-2">
                @forelse ($events as $event)
                    <x-ui.item variant="outline" class="items-start" wire:key="event-{{ $event->id }}">
                        <x-ui.item-content class="min-w-0">
                            <x-ui.item-title class="flex flex-wrap items-center gap-2 text-sm">
                                {{ $event->type->name }}
                                <span class="font-normal tabular-nums text-muted-foreground">{{ $date($event->effective_date) }}</span>
                            </x-ui.item-title>
                            <x-ui.item-description class="flex flex-col gap-0.5 text-sm">
                                @if ($event->to_department_id && $event->from_department_id)
                                    <span>{{ __('Department') }}: {{ $event->fromDepartment?->name }} → {{ $event->toDepartment?->name }}</span>
                                @elseif ($event->to_department_id)
                                    <span>{{ __('Department') }}: {{ $event->toDepartment?->name }}</span>
                                @endif
                                @if ($event->to_designation_id && $event->from_designation_id)
                                    <span>{{ __('Designation') }}: {{ $event->fromDesignation?->name }} → {{ $event->toDesignation?->name }}</span>
                                @elseif ($event->to_designation_id)
                                    <span>{{ __('Designation') }}: {{ $event->toDesignation?->name }}</span>
                                @endif
                                @if ($canViewSalary && $event->to_salary !== null)
                                    <span class="tabular-nums">{{ __('Salary') }}: {{ $event->from_salary !== null ? \App\Support\Money::format($event->from_salary).' → ' : '' }}{{ \App\Support\Money::format($event->to_salary) }}</span>
                                @endif
                                @if ($event->note)
                                    <span class="whitespace-pre-line">{{ $event->note }}</span>
                                @endif
                                @if ($event->approver)
                                    <span class="text-muted-foreground">{{ __('By :name', ['name' => $event->approver->name]) }}</span>
                                @endif
                            </x-ui.item-description>
                        </x-ui.item-content>
                    </x-ui.item>
                @empty
                    <p class="py-8 text-center text-sm text-muted-foreground">{{ __('No employment events yet.') }}</p>
                @endforelse
            </x-ui.item-group>
        @elseif ($tab === 'education')
            <x-ui.card class="p-4 md:p-6">
                <h2 class="text-base font-semibold">{{ __('Education') }}</h2>
                <x-ui.item-group class="gap-2">
                    @forelse ($education as $row)
                        <x-ui.item variant="outline" wire:key="education-{{ $row->id }}">
                            <x-ui.item-content>
                                <x-ui.item-title class="text-sm">{{ $row->degree }}</x-ui.item-title>
                                <x-ui.item-description class="text-sm">{{ collect([$row->institution, collect([$row->from_year, $row->to_year])->filter()->implode('–'), $row->result])->filter()->implode(' · ') }}</x-ui.item-description>
                            </x-ui.item-content>
                        </x-ui.item>
                    @empty
                        <p class="text-sm text-muted-foreground">{{ __('No education added.') }}</p>
                    @endforelse
                </x-ui.item-group>

                <h2 class="mt-4 text-base font-semibold">{{ __('Experience') }}</h2>
                <x-ui.item-group class="gap-2">
                    @forelse ($experience as $row)
                        <x-ui.item variant="outline" wire:key="experience-{{ $row->id }}">
                            <x-ui.item-content>
                                <x-ui.item-title class="text-sm">{{ $row->position }}</x-ui.item-title>
                                <x-ui.item-description class="text-sm">{{ collect([$row->company, collect([$row->from_date?->format('M Y'), $row->to_date?->format('M Y')])->filter()->implode('–')])->filter()->implode(' · ') }}</x-ui.item-description>
                            </x-ui.item-content>
                        </x-ui.item>
                    @empty
                        <p class="text-sm text-muted-foreground">{{ __('No experience added.') }}</p>
                    @endforelse
                </x-ui.item-group>
            </x-ui.card>
        @elseif ($tab === 'salary')
            <x-ui.card class="p-4 md:p-6">
                <x-ui.description-list>
                    <x-ui.description-item :term="__('Gross salary')"><span class="tabular-nums">{{ $employee->gross_salary !== null ? \App\Support\Money::format($employee->gross_salary) : '—' }}</span></x-ui.description-item>
                    <x-ui.description-item :term="__('Bank')">{{ $employee->bank_name ?? '—' }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Account no.')"><span class="tabular-nums">{{ $employee->bank_account_no ?? '—' }}</span></x-ui.description-item>
                    <x-ui.description-item :term="__('Mobile wallet')"><span class="tabular-nums">{{ $employee->mobile_wallet_no ?? '—' }}</span></x-ui.description-item>
                </x-ui.description-list>
            </x-ui.card>
        @elseif ($tab === 'notes')
            <livewire:foundation.notes :model="$employee" :key="'notes-'.$employee->id" />
        @else
            <livewire:foundation.history :model="$employee" :key="'history-'.$employee->id" />
        @endif
    </div>

    <x-shell.sheet id="employee-actions" :title="__('Employee actions')" :description="$employee->employee_code.' · '.$employee->full_name">
        <div class="flex flex-col gap-2 pb-4">
            @can('update', $employee)
                <x-ui.button class="h-11 justify-start" variant="outline" :href="route('hrm.employees.edit', $employee)" wire:navigate><x-lucide-pencil /> {{ __('Edit') }}</x-ui.button>
            @endcan
            @can('manageHistory', $employee)
                <x-ui.button class="h-11 justify-start" variant="outline" x-on:click="$dispatch('close-sheet-employee-actions'); $dispatch('open-sheet-employment-event')"><x-lucide-history /> {{ __('Record event') }}</x-ui.button>
            @endcan
            @can('deactivate', $employee)
                @if ($exited)
                    <x-ui.button class="h-11 justify-start" variant="outline" x-on:click="$dispatch('close-sheet-employee-actions'); $dispatch('open-sheet-rejoin')"><x-lucide-undo-2 /> {{ __('Rejoin') }}</x-ui.button>
                @elseif (Route::has('hrm.employees.exit'))
                    <x-ui.button class="h-11 justify-start text-destructive" variant="outline" :href="route('hrm.employees.exit', $employee)" wire:navigate><x-lucide-log-out /> {{ __('Exit') }}</x-ui.button>
                @endif
            @endcan
            @if (! $linked && $user->can('admin.users.update'))
                <x-ui.button class="h-11 justify-start" variant="outline" x-on:click="$dispatch('close-sheet-employee-actions'); $dispatch('open-sheet-link-user')"><x-lucide-link /> {{ __('Link user') }}</x-ui.button>
            @endif
            <x-ui.button class="h-11 justify-start" variant="outline" href="tel:{{ $employee->phone }}"><x-lucide-phone /> {{ __('Call') }}</x-ui.button>
        </div>
    </x-shell.sheet>

    @can('manageHistory', $employee)
        <x-shell.sheet id="employment-event" :title="__('Record event')" :description="__('Add an entry to :name’s employment history.', ['name' => $employee->full_name])">
            <form id="employment-event-form" wire:submit="recordEvent" class="flex flex-col gap-4">
                <x-ui.field>
                    <x-ui.field-label for="event-type">{{ __('Event') }} *</x-ui.field-label>
                    <x-ui.select native id="event-type" wire:model="eventForm.employment_event_type_id" class="{{ $input }}">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($eventTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('eventForm.employment_event_type_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="event-date">{{ __('Effective date') }} *</x-ui.field-label>
                    <x-ui.input id="event-date" type="date" wire:model="eventForm.effective_date" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('eventForm.effective_date')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="event-note">{{ __('Note') }}</x-ui.field-label>
                    <x-ui.textarea id="event-note" wire:model="eventForm.note" rows="2" class="text-base md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('eventForm.note')" />
                </x-ui.field>
                <p class="text-sm text-muted-foreground">{{ __('To change department, designation or salary, edit the employee.') }}</p>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" class="h-11 md:h-9" x-on:click="$dispatch('close-sheet-employment-event')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="employment-event-form" class="h-11 md:h-9">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endcan

    @can('deactivate', $employee)
        <x-shell.sheet id="rejoin" :title="__('Rejoin')" :description="__('Bring :name back as an active employee.', ['name' => $employee->full_name])">
            <form id="rejoin-form" wire:submit="rejoin" class="flex flex-col gap-4">
                <x-ui.field>
                    <x-ui.field-label for="rejoin-date">{{ __('Rejoining date') }} *</x-ui.field-label>
                    <x-ui.input id="rejoin-date" type="date" wire:model="rejoinForm.effective_date" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('rejoinForm.effective_date')" />
                    <x-ui.field-error :messages="$errors->get('rejoinForm.employee_status_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="rejoin-note">{{ __('Note') }}</x-ui.field-label>
                    <x-ui.textarea id="rejoin-note" wire:model="rejoinForm.note" rows="2" class="text-base md:text-sm" />
                </x-ui.field>
                <p class="text-sm text-muted-foreground">{{ __('The login stays off until an admin turns it on.') }}</p>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" class="h-11 md:h-9" x-on:click="$dispatch('close-sheet-rejoin')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="rejoin-form" class="h-11 md:h-9">{{ __('Rejoin') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endcan

    @can('admin.users.update')
        <x-shell.sheet id="link-user" :title="__('Link user')" :description="__('Connect :name to a login account.', ['name' => $employee->full_name])">
            <form id="link-user-form" wire:submit="linkUser" class="flex flex-col gap-4">
                <x-ui.field>
                    <x-ui.field-label for="link-user">{{ __('User') }} *</x-ui.field-label>
                    <x-ui.select native id="link-user" wire:model="linkUserId" class="{{ $input }}">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($linkableUsers as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->username }})</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('linkUserId')" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" class="h-11 md:h-9" x-on:click="$dispatch('close-sheet-link-user')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="link-user-form" class="h-11 md:h-9">{{ __('Link') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endcan
</div>
