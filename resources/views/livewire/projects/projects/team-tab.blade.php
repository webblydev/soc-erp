@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
@endphp

<div class="flex flex-col gap-4">
    @if ($canManage)
        <x-ui.button class="h-11 self-start md:h-9" x-on:click="$dispatch('open-sheet-team-add')"><x-lucide-user-plus /> {{ __('Add member') }}</x-ui.button>
    @endif

    <x-ui.item-group class="gap-2">
        @forelse ($active as $member)
            <x-ui.item variant="outline" class="min-h-16" wire:key="member-{{ $member->id }}">
                <x-employee-avatar :employee="$member->employee" />
                <x-ui.item-content class="min-w-0">
                    <x-ui.item-title class="text-base md:text-sm"><span class="truncate">{{ $member->employee->full_name }}</span></x-ui.item-title>
                    <x-ui.item-description class="text-sm">
                        {{ $member->role->name }} · {{ $member->employee->designation->name }}
                        @if ($member->allocation_pct !== null) · {{ rtrim(rtrim($member->allocation_pct, '0'), '.') }} % @endif
                    </x-ui.item-description>
                    <x-ui.item-description class="text-sm tabular-nums">{{ __('Since :date', ['date' => $member->assigned_on->format('d-M-Y')]) }}</x-ui.item-description>
                </x-ui.item-content>
                @if ($canManage)
                    <x-ui.button size="sm" variant="outline" class="h-11 shrink-0 md:h-8" wire:click="confirmRelease({{ $member->id }})">{{ __('Release') }}</x-ui.button>
                @endif
            </x-ui.item>
        @empty
            <p class="py-8 text-center text-sm text-muted-foreground">{{ __('No team members yet.') }}</p>
        @endforelse
    </x-ui.item-group>

    @if ($past->isNotEmpty())
        <x-ui.collapsible>
            <x-ui.collapsible-trigger class="flex min-h-11 items-center gap-2 text-sm font-medium">
                <x-lucide-chevron-down class="size-4" /> {{ trans_choice(':count past member|:count past members', $past->count()) }}
            </x-ui.collapsible-trigger>
            <x-ui.collapsible-content>
                <x-ui.item-group class="gap-2 pt-2">
                    @foreach ($past as $member)
                        <x-ui.item variant="outline" size="sm" class="opacity-75" wire:key="past-member-{{ $member->id }}">
                            <x-ui.item-content class="min-w-0">
                                <x-ui.item-title class="text-sm">{{ $member->employee->full_name }}</x-ui.item-title>
                                <x-ui.item-description class="text-sm tabular-nums">{{ $member->role->name }} · {{ $member->assigned_on->format('d-M-Y') }} – {{ $member->released_on?->format('d-M-Y') }}</x-ui.item-description>
                            </x-ui.item-content>
                        </x-ui.item>
                    @endforeach
                </x-ui.item-group>
            </x-ui.collapsible-content>
        </x-ui.collapsible>
    @endif

    @if ($canManage)
        <x-shell.sheet id="team-add" :title="__('Add team member')" :description="__('Give an employee a role on :number.', ['number' => $project->project_number])">
            <form id="team-add-form" wire:submit="addMember" class="flex flex-col gap-4">
                <x-ui.field>
                    <x-ui.field-label for="member-employee">{{ __('Employee') }} *</x-ui.field-label>
                    <x-employee-select id="member-employee" wire:model="memberForm.employee_id" :placeholder="__('Choose…')" />
                    <x-ui.field-error :messages="$errors->get('memberForm.employee_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="member-role">{{ __('Role') }} *</x-ui.field-label>
                    <x-lookup-select table="project_roles" :placeholder="__('Choose…')" id="member-role" wire:model="memberForm.project_role_id" />
                    <x-ui.field-error :messages="$errors->get('memberForm.project_role_id')" />
                </x-ui.field>
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.field>
                        <x-ui.field-label for="member-allocation">{{ __('Allocation %') }}</x-ui.field-label>
                        <x-ui.input id="member-allocation" wire:model="memberForm.allocation_pct" inputmode="decimal" class="{{ $input }} tabular-nums" />
                        <x-ui.field-error :messages="$errors->get('memberForm.allocation_pct')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="member-from">{{ __('Assigned on') }} *</x-ui.field-label>
                        <x-ui.input type="date" id="member-from" wire:model="memberForm.assigned_on" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('memberForm.assigned_on')" />
                    </x-ui.field>
                </div>
                <x-ui.field>
                    <x-ui.field-label for="member-notes">{{ __('Note') }}</x-ui.field-label>
                    <x-ui.input id="member-notes" wire:model="memberForm.notes" class="{{ $input }}" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-team-add')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="team-add-form">{{ __('Add') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>

        <x-shell.sheet id="team-release" :title="__('Release member')" :description="__('The member leaves the active team; their history stays.')">
            <form id="team-release-form" wire:submit="release" class="flex flex-col gap-4">
                <x-ui.field>
                    <x-ui.field-label for="member-release">{{ __('Release date') }} *</x-ui.field-label>
                    <x-ui.input type="date" id="member-release" wire:model="releasedOn" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('releasedOn')" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-team-release')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="team-release-form">{{ __('Release') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endif
</div>
