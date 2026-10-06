@php
    $windows = [['value' => 'expired', 'label' => __('Expired')], ['value' => '30', 'label' => __('30 days')], ['value' => '60', 'label' => __('60 days')], ['value' => '90', 'label' => __('90 days')]];
    $daysLeft = fn ($document) => (int) today()->diffInDays($document->expiry_date, false);
@endphp

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <h1 class="hidden text-2xl font-semibold tracking-tight md:block">{{ __('Expiring documents') }}</h1>
        <div class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
            <x-ui.segmented-control name="expiry-window" wire:model.live="window" :value="$window" class="h-11 md:h-9" :options="$windows" />
        </div>
    </div>

    <div class="hidden md:block" x-data>
        <x-shell.bulk-bar exportable deletable />

        <x-ui.table variant="bordered">
            <x-ui.table-header>
                <x-ui.table-row>
                    <x-shell.select-all :ids="$documents->pluck('id')" />
                    <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                    <x-ui.table-head>{{ __('Employee') }}</x-ui.table-head>
                    <x-ui.table-head>{{ __('Document') }}</x-ui.table-head>
                    <x-ui.table-head>{{ __('Number') }}</x-ui.table-head>
                    <x-ui.table-head>{{ __('Expiry date') }}</x-ui.table-head>
                    <x-ui.table-head class="text-end">{{ __('Days left') }}</x-ui.table-head>
                </x-ui.table-row>
            </x-ui.table-header>
            <x-ui.table-body>
                @forelse ($documents as $document)
                    <x-ui.table-row wire:key="document-{{ $document->id }}">
                        <x-shell.select-row :id="$document->id" :label="$document->employee->full_name.' · '.$document->type->name" />
                        <x-shell.row-menu>
                            <x-shell.row-menu-item icon="eye" data-detail-modal :href="route('hrm.employees.show', [$document->employee, 'tab' => 'documents'])">{{ __('View employee') }}</x-shell.row-menu-item>
                            <x-shell.row-menu-item icon="trash-2" destructive wire:click="deleteRecord({{ $document->id }})" wire:confirm="{{ __('Delete this :type?', ['type' => $document->type->name]) }}">{{ __('Delete') }}</x-shell.row-menu-item>
                        </x-shell.row-menu>
                        <x-ui.table-cell class="font-medium">
                            <a data-detail-modal href="{{ route('hrm.employees.show', [$document->employee, 'tab' => 'documents']) }}" wire:navigate class="hover:underline">{{ $document->employee->full_name }}</a>
                            <span class="ms-1 font-mono text-sm text-muted-foreground">{{ $document->employee->employee_code }}</span>
                        </x-ui.table-cell>
                        <x-ui.table-cell>{{ $document->type->name }}</x-ui.table-cell>
                        <x-ui.table-cell class="font-mono text-sm">{{ $document->document_no ?? '—' }}</x-ui.table-cell>
                        <x-ui.table-cell class="tabular-nums">{{ $document->expiry_date->format('d-M-Y') }}</x-ui.table-cell>
                        <x-ui.table-cell @class(['text-end tabular-nums', 'text-destructive font-medium' => $daysLeft($document) < 0])>{{ $daysLeft($document) }}</x-ui.table-cell>
                    </x-ui.table-row>
                @empty
                    <x-ui.table-row>
                        <x-ui.table-cell colspan="7" class="py-10 text-center text-muted-foreground">{{ __('No documents in this window.') }}</x-ui.table-cell>
                    </x-ui.table-row>
                @endforelse
            </x-ui.table-body>
        </x-ui.table>
    </div>

    <x-ui.item-group class="gap-2 md:hidden">
        @forelse ($documents as $document)
            <x-ui.item variant="outline" class="min-h-16 active:bg-accent" :href="route('hrm.employees.show', [$document->employee, 'tab' => 'documents'])" wire:navigate wire:key="m-document-{{ $document->id }}">
                <x-ui.item-content class="min-w-0">
                    <x-ui.item-title class="text-base"><span class="truncate">{{ $document->employee->full_name }}</span></x-ui.item-title>
                    <x-ui.item-description class="text-sm">{{ $document->type->name }} · <span class="tabular-nums">{{ $document->expiry_date->format('d-M-Y') }}</span></x-ui.item-description>
                </x-ui.item-content>
                <x-ui.badge :tone="$daysLeft($document) < 0 ? 'danger' : 'warning'" class="shrink-0 text-sm">
                    {{ $daysLeft($document) < 0 ? __('Expired') : trans_choice(':count day|:count days', $daysLeft($document)) }}
                </x-ui.badge>
                <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
            </x-ui.item>
        @empty
            <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No documents in this window.') }}</p>
        @endforelse
    </x-ui.item-group>
</div>
