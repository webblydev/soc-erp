@php
    use App\Modules\Estimation\Models\MbStatus;

    $user = auth()->user();
    $money = fn ($value) => \App\Support\Money::format($value);
    $qty = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    $status = $entry->status->code;
    $editable = in_array($status, [MbStatus::RECORDED, MbStatus::REJECTED], true);

    $actions = array_values(array_filter([
        $editable && $user->can('update', $entry) ? ['label' => __('Edit'), 'icon' => 'pencil', 'href' => route('site.mb.edit', $entry), 'modal' => true] : null,
        $status === MbStatus::RECORDED && $user->can('verify', $entry) ? ['label' => __('Verify'), 'icon' => 'check', 'click' => '$wire.verify()', 'primary' => true] : null,
        $status === MbStatus::RECORDED && $user->can('verify', $entry) ? ['label' => __('Reject'), 'icon' => 'x', 'click' => "\$dispatch('open-sheet-mb-entry-reject')"] : null,
        $status === MbStatus::VERIFIED && $entry->running_bill_line_id === null && $user->can('verify', $entry) ? ['label' => __('Unverify'), 'icon' => 'undo-2', 'click' => '$wire.unverify()'] : null,
        $editable && $user->can('delete', $entry) ? ['label' => __('Delete'), 'icon' => 'trash-2', 'click' => "\$dispatch('open-sheet-mb-entry-delete')", 'destructive' => true] : null,
    ]));
    $primary = collect($actions)->firstWhere('primary', true);
@endphp

<x-slot:actions>
    <x-ui.button variant="ghost" size="icon" class="size-11" x-on:click="$dispatch('open-sheet-mb-entry-actions')" :aria-label="__('Entry actions')">
        <x-lucide-ellipsis-vertical class="size-5" />
    </x-ui.button>
</x-slot:actions>

<div class="flex flex-col gap-6 pb-24 md:pb-0">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 flex-col gap-1">
            <span class="font-mono text-sm text-muted-foreground">{{ $entry->mb_number }}</span>
            <h1 class="text-xl font-semibold tracking-tight md:text-2xl">{{ $entry->description }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-ui.badge :tone="$entry->status->color ?? 'neutral'" class="text-sm">{{ $entry->status->name }}</x-ui.badge>
                <a data-detail-modal href="{{ route('projects.projects.show', ['project' => $entry->project, 'tab' => 'site']) }}" wire:navigate class="font-medium hover:underline"><span class="font-mono">{{ $entry->project->project_number }}</span> · {{ $entry->project->name }}</a>
            </div>
        </div>
        @include('livewire.estimation.partials.action-buttons', ['actions' => $actions])
    </div>

    @if ($status === MbStatus::REJECTED && $entry->rejection_reason)
        <x-ui.alert><x-lucide-message-square-warning /><x-ui.alert-title>{{ __('Rejected') }}</x-ui.alert-title><x-ui.alert-description>{{ $entry->rejection_reason }}</x-ui.alert-description></x-ui.alert>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Quantity') }}</span>
            <span class="text-lg font-semibold tabular-nums">{{ $qty($entry->quantity) }} {{ $entry->unit->symbol }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Amount') }}</span>
            <span class="text-lg font-semibold tabular-nums">{{ $money($entry->amount) }}</span>
        </x-ui.card>
        @if ($progress)
            <x-ui.card class="col-span-2 gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('BOQ :line · :number', ['line' => $entry->estimateLine->line_no, 'number' => $entry->estimateLine->estimate->estimate_number]) }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ $qty($progress['cumulative']) }} / {{ $qty($progress['boq']) }} {{ $entry->estimateLine->unit->symbol }} ({{ $progress['pct'] !== null ? round((float) $progress['pct'], 1) : '—' }}%)</span>
                <x-ui.progress :value="min(100, (float) ($progress['pct'] ?? 0))" class="h-1.5" />
            </x-ui.card>
        @endif
    </div>

    <x-ui.card class="p-4 md:p-6">
        <x-ui.description-list>
            <x-ui.description-item :term="__('Measured on')">{{ $entry->measured_on->format('d-M-Y') }}</x-ui.description-item>
            <x-ui.description-item :term="__('Measured by')">{{ $entry->measurer->full_name }}</x-ui.description-item>
            <x-ui.description-item :term="__('MB book / page')">{{ collect([$entry->mb_book_no, $entry->mb_page_no])->filter()->implode(' / ') ?: '—' }}</x-ui.description-item>
            <x-ui.description-item :term="__('Work item')">{{ $entry->workItem ? $entry->workItem->code.' · '.$entry->workItem->name : '—' }}</x-ui.description-item>
            <x-ui.description-item :term="__('Location')">{{ $entry->location ?? '—' }}</x-ui.description-item>
            <x-ui.description-item :term="__('Measurement')">
                @if ($entry->measurement_formula->value === 'manual')
                    {{ __('Manual') }}
                @else
                    {{ collect(['nos', 'length', 'width', 'height'])->map(fn ($field) => $entry->{$field})->filter(fn ($value) => $value !== null)->map($qty)->implode(' × ') }}
                @endif
            </x-ui.description-item>
            <x-ui.description-item :term="__('Rate')">{{ \App\Support\Money::formatRate($entry->rate) }}</x-ui.description-item>
            <x-ui.description-item :term="__('Verified')">{{ $entry->verifier ? $entry->verifier->name.' · '.$entry->verified_at?->format('d-M-Y') : '—' }}</x-ui.description-item>
            @if ($entry->remarks)<x-ui.description-item :term="__('Remarks')">{{ $entry->remarks }}</x-ui.description-item>@endif
        </x-ui.description-list>
    </x-ui.card>

    <livewire:foundation.attachments :model="$entry" :key="'attachments-'.$entry->id" />
    <livewire:foundation.history :model="$entry" :key="'history-'.$entry->id" />

    @if ($primary)
        <div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 border-t bg-background px-4 py-3 md:hidden">
            <x-ui.button class="h-11 w-full" x-on:click="{{ $primary['click'] }}"><x-dynamic-component :component="'lucide-'.$primary['icon']" /> {{ $primary['label'] }}</x-ui.button>
        </div>
    @endif

    @include('livewire.estimation.partials.action-sheet', ['actions' => $actions, 'sheet' => 'mb-entry-actions', 'title' => __('Entry actions'), 'description' => $entry->mb_number])

    <x-shell.sheet id="mb-entry-reject" :title="__('Reject measurement')" :description="__('The measurer corrects it and records it again.')">
        <x-ui.field>
            <x-ui.field-label for="entry-reject-reason">{{ __('Reason') }} *</x-ui.field-label>
            <x-ui.textarea id="entry-reject-reason" wire:model="rejectReason" rows="2" class="text-base md:text-sm" />
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-mb-entry-reject')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button variant="destructive" wire:click="reject">{{ __('Reject') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    @can('delete', $entry)
        <x-shell.confirm id="mb-entry-delete" :title="__('Delete measurement?')" :description="__('Only recorded or rejected entries can be deleted.')">
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-mb-entry-delete')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="destructive" wire:click="delete">{{ __('Delete') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.confirm>
    @endcan
</div>
