@php
    $date = fn ($value) => $value?->format('d-M-Y') ?? '—';
    $pendingDays = fn ($approval) => $approval->isFinal() ? null : $approval->daysElapsed();
@endphp

<div>
    <x-shell.list
        :search-placeholder="__('Search reference no.')"
        :create-url="auth()->user()->can('projects.approvals.manage') ? route('projects.approvals.create') : null"
        :create-label="__('New approval')"
        :exportable="true"
        :has-more="$this->hasMoreRows"
        :active-filters="count(array_filter($filters, 'filled'))"
        :filter-columns="2"
    >
        <x-slot:filters>
            <x-ui.field>
                <x-ui.field-label for="filter-state">{{ __('Show') }}</x-ui.field-label>
                <x-ui.select native id="filter-state" wire:model.live="filters.state" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Pending') }}</option>
                    <option value="overdue">{{ __('Overdue') }}</option>
                    <option value="final">{{ __('Final') }}</option>
                    <option value="all">{{ __('All') }}</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-authority">{{ __('Authority') }}</x-ui.field-label>
                <x-lookup-select table="approval_authorities" :placeholder="__('Any authority')" id="filter-authority" wire:model.live="filters.authority" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-type">{{ __('Type') }}</x-ui.field-label>
                <x-lookup-select table="approval_types" :placeholder="__('Any type')" id="filter-type" wire:model.live="filters.type" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-status">{{ __('Status') }}</x-ui.field-label>
                <x-lookup-select table="approval_statuses" :placeholder="__('Any status')" id="filter-status" wire:model.live="filters.status" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="filter-responsible">{{ __('Responsible') }}</x-ui.field-label>
                <x-ui.select native id="filter-responsible" wire:model.live="filters.responsible" class="h-11 text-base md:h-9 md:text-sm">
                    <option value="">{{ __('Anyone') }}</option>
                    @foreach ($responsibles as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
        </x-slot:filters>

        <x-slot:desktop>
            <div class="overflow-x-auto">
                <x-ui.table variant="bordered">
                    <x-ui.table-header>
                        <x-ui.table-row>
                            <x-ui.table-head>{{ __('Project') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Authority') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Type') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Reference') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Responsible') }}</x-ui.table-head>
                            <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="submitted" :label="__('Submitted')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head><x-shell.sort-header key="expected" :label="__('Expected')" :$sort :$direction /></x-ui.table-head>
                            <x-ui.table-head class="text-end">{{ __('Days pending') }}</x-ui.table-head>
                        </x-ui.table-row>
                    </x-ui.table-header>
                    <x-ui.table-body>
                        @forelse ($rows as $approval)
                            <x-ui.table-row wire:key="approval-{{ $approval->id }}">
                                <x-ui.table-cell class="font-mono text-sm whitespace-nowrap">
                                    <a data-detail-modal href="{{ route('projects.projects.show', ['project' => $approval->project, 'tab' => 'approvals']) }}" wire:navigate class="hover:underline">{{ $approval->project->project_number }}</a>
                                </x-ui.table-cell>
                                <x-ui.table-cell>{{ $approval->authority->name }}</x-ui.table-cell>
                                <x-ui.table-cell class="font-medium">
                                    <a data-detail-modal href="{{ route('projects.approvals.show', $approval) }}" wire:navigate class="hover:underline">{{ $approval->type->name }}</a>
                                </x-ui.table-cell>
                                <x-ui.table-cell>{{ $approval->reference_no ?? '—' }}</x-ui.table-cell>
                                <x-ui.table-cell>{{ $approval->responsible?->full_name ?? '—' }}</x-ui.table-cell>
                                <x-ui.table-cell>
                                    <x-ui.badge :tone="$approval->status->color ?? 'neutral'">{{ $approval->status->name }}</x-ui.badge>
                                    @if ($approval->isOverdue())<x-ui.badge tone="danger" class="ms-1">{{ __('Overdue') }}</x-ui.badge>@endif
                                </x-ui.table-cell>
                                <x-ui.table-cell class="tabular-nums whitespace-nowrap">{{ $date($approval->submitted_on) }}</x-ui.table-cell>
                                <x-ui.table-cell class="tabular-nums whitespace-nowrap">{{ $date($approval->expected_on) }}</x-ui.table-cell>
                                <x-ui.table-cell class="text-end tabular-nums">{{ $pendingDays($approval) ?? '—' }}</x-ui.table-cell>
                            </x-ui.table-row>
                        @empty
                            <x-ui.table-row>
                                <x-ui.table-cell colspan="9" class="py-10 text-center text-muted-foreground">{{ __('No approvals found.') }}</x-ui.table-cell>
                            </x-ui.table-row>
                        @endforelse
                    </x-ui.table-body>
                </x-ui.table>
            </div>
            <div class="mt-4">{{ $rows->links() }}</div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $approval)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-approval-{{ $approval->id }}" :href="route('projects.approvals.show', $approval)" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-base"><span class="truncate">{{ $approval->type->name }}</span></x-ui.item-title>
                        <x-ui.item-description class="truncate text-sm"><span class="font-mono">{{ $approval->project->project_number }}</span> · {{ $approval->authority->name }}</x-ui.item-description>
                        <span class="text-sm tabular-nums text-muted-foreground">{{ __('Expected') }} {{ $date($approval->expected_on) }}</span>
                    </x-ui.item-content>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <x-ui.badge :tone="$approval->status->color ?? 'neutral'" class="text-sm">{{ $approval->status->name }}</x-ui.badge>
                        @if ($approval->isOverdue())<x-ui.badge tone="danger" class="text-sm">{{ __('Overdue') }}</x-ui.badge>@endif
                    </div>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No approvals found.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
