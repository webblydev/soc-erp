@php
    use App\Modules\Estimation\Models\EstimateKind;
    use App\Modules\Estimation\Models\EstimateStatus;

    $user = auth()->user();
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $money = fn ($value) => \App\Support\Money::format($value, false);
    $qty = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    $status = $estimate->status->code;
    $editable = in_array($status, [EstimateStatus::DRAFT, EstimateStatus::REJECTED], true);
    $isLatest = $family->last()?->id === $estimate->id;

    $actions = array_values(array_filter([
        $editable && $user->can('update', $estimate) ? ['label' => __('Edit'), 'icon' => 'pencil', 'href' => route('estimation.estimates.edit', $estimate), 'modal' => true, 'primary' => true] : null,
        $status === EstimateStatus::DRAFT && $user->can('submit', $estimate) ? ['label' => __('Submit'), 'icon' => 'send', 'click' => '$wire.submit()'] : null,
        $status === EstimateStatus::SUBMITTED && $user->can('approve', $estimate) ? ['label' => __('Approve'), 'icon' => 'check', 'click' => '$wire.approve()', 'primary' => true] : null,
        $status === EstimateStatus::SUBMITTED && $user->can('approve', $estimate) ? ['label' => __('Reject'), 'icon' => 'x', 'click' => "\$dispatch('open-sheet-estimate-reject')"] : null,
        $status === EstimateStatus::APPROVED && $isLatest && $user->can('revise', $estimate) ? ['label' => __('Revise'), 'icon' => 'git-branch', 'click' => "\$dispatch('open-sheet-estimate-revise')"] : null,
        $status === EstimateStatus::APPROVED && in_array($estimate->kind->code, [EstimateKind::BOQ, EstimateKind::MATERIAL], true) && $user->can('manageBudget', $estimate->project)
            ? ['label' => __('Create budget'), 'icon' => 'wallet', 'click' => '$wire.buildBudget()'] : null,
        $family->count() > 1 ? ['label' => __('Compare'), 'icon' => 'git-compare', 'href' => route('estimation.estimates.compare', ['estimate' => $estimate, 'with' => ($previous ?? $family->first())->estimate_number])] : null,
        $user->can('print', $estimate) ? ['label' => __('Print'), 'icon' => 'printer', 'click' => "\$dispatch('open-sheet-estimate-print')"] : null,
        $user->can('export', $estimate) ? ['label' => __('Export Excel'), 'icon' => 'download', 'href' => route('estimation.estimates.export', $estimate), 'external' => true] : null,
        $editable && $user->can('delete', $estimate) ? ['label' => __('Delete'), 'icon' => 'trash-2', 'click' => "\$dispatch('open-sheet-estimate-delete')", 'destructive' => true] : null,
    ]));
    $primary = collect($actions)->firstWhere('primary', true);
@endphp

<x-slot:actions>
    <x-ui.button variant="ghost" size="icon" class="size-11" x-on:click="$dispatch('open-sheet-estimate-actions')" :aria-label="__('Estimate actions')">
        <x-lucide-ellipsis-vertical class="size-5" />
    </x-ui.button>
</x-slot:actions>

<div class="flex flex-col gap-6 pb-24 md:pb-0">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 flex-col gap-1">
            <span class="font-mono text-sm text-muted-foreground">{{ $estimate->estimate_number }}</span>
            <h1 class="text-xl font-semibold tracking-tight md:text-2xl">{{ $estimate->title }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-ui.badge :tone="$estimate->status->color ?? 'neutral'" class="text-sm">{{ $estimate->status->name }}</x-ui.badge>
                <x-ui.badge variant="outline" class="text-sm">{{ __('Rev. :n', ['n' => $estimate->revision_no]) }}</x-ui.badge>
                <span>{{ $estimate->kind->name }}</span>
                <a data-detail-modal href="{{ route('projects.projects.show', ['project' => $estimate->project, 'tab' => 'estimates']) }}" wire:navigate class="font-medium hover:underline">
                    <span class="font-mono">{{ $estimate->project->project_number }}</span> · {{ $estimate->project->name }}
                </a>
            </div>
        </div>
        @include('livewire.estimation.partials.action-buttons', ['actions' => $actions])
    </div>

    @if ($status === EstimateStatus::REJECTED && $estimate->rejection_note)
        <x-ui.alert>
            <x-lucide-message-square-warning />
            <x-ui.alert-title>{{ __('Rejected') }}</x-ui.alert-title>
            <x-ui.alert-description>{{ $estimate->rejection_note }}</x-ui.alert-description>
        </x-ui.alert>
    @endif

    @if ($family->count() > 1)
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0" aria-label="{{ __('Revisions') }}">
            @foreach ($family as $revision)
                <a href="{{ route('estimation.estimates.show', $revision->estimate_number) }}" wire:navigate wire:key="revision-{{ $revision->id }}"
                   @class(['flex min-h-11 shrink-0 items-center gap-2 rounded-md border px-3 text-sm', 'border-primary bg-accent' => $revision->id === $estimate->id])>
                    <span class="font-medium">{{ __('Rev. :n', ['n' => $revision->revision_no]) }}</span>
                    <x-ui.badge :tone="$revision->status->color ?? 'neutral'">{{ $revision->status->name }}</x-ui.badge>
                </a>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Subtotal') }}</span>
            <span class="text-lg font-semibold tabular-nums">{{ $money($estimate->subtotal) }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Total') }}</span>
            <span class="text-lg font-semibold tabular-nums">{{ $money($estimate->total_amount) }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Lines') }}</span>
            <span class="text-lg font-semibold tabular-nums">{{ $estimate->lines->count() + $estimate->materialLines->count() }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Previous revision') }}</span>
            @if ($previous)
                @php($difference = bcsub((string) $estimate->total_amount, (string) $previous->total_amount, 2))
                <span class="text-lg font-semibold tabular-nums">{{ $money($previous->total_amount) }}</span>
                <span @class(['text-sm tabular-nums', 'text-destructive' => str_starts_with($difference, '-')])>{{ str_starts_with($difference, '-') ? '' : '+' }}{{ $money($difference) }}</span>
            @else
                <span class="text-lg font-semibold">—</span>
            @endif
        </x-ui.card>
    </div>

    <div class="flex min-w-0 flex-col gap-4">
        <div class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
            <x-ui.segmented-control name="estimate-tab" wire:model.live="tab" :value="$tab" class="h-11 md:h-9"
                :options="collect($tabs)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->all()" />
        </div>

        @if ($tab === 'lines')
            @foreach ($groups as $sectionName => $lines)
                <x-ui.card class="gap-3 p-4 md:p-6" wire:key="group-{{ $loop->index }}">
                    @if ($sectionName !== '')
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold">{{ $sectionName }}</h2>
                            <span class="text-sm tabular-nums text-muted-foreground">{{ $money($lines->reduce(fn ($sum, $line) => bcadd($sum, (string) $line->amount, 2), '0')) }}</span>
                        </div>
                    @endif
                    <div class="hidden overflow-x-auto md:block">
                        <x-ui.table variant="bordered">
                            <x-ui.table-header>
                                <x-ui.table-row>
                                    <x-ui.table-head>{{ __('No.') }}</x-ui.table-head>
                                    <x-ui.table-head>{{ __('Description') }}</x-ui.table-head>
                                    <x-ui.table-head>{{ __('Level / location') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('Nos') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('L') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('W') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('H') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('Qty') }}</x-ui.table-head>
                                    <x-ui.table-head>{{ __('Unit') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('Rate') }}</x-ui.table-head>
                                    <x-ui.table-head class="text-end">{{ __('Amount') }}</x-ui.table-head>
                                </x-ui.table-row>
                            </x-ui.table-header>
                            <x-ui.table-body>
                                @foreach ($lines as $line)
                                    <x-ui.table-row wire:key="line-{{ $line->id }}" @class(['text-destructive' => $line->deduction])>
                                        <x-ui.table-cell class="font-mono text-sm">{{ $line->line_no }}</x-ui.table-cell>
                                        <x-ui.table-cell>
                                            {{ $line->description }}
                                            @if ($line->workItem)<span class="block font-mono text-sm text-muted-foreground">{{ $line->workItem->code }}</span>@endif
                                            @if ($line->remarks)<span class="block text-sm text-muted-foreground">{{ $line->remarks }}</span>@endif
                                        </x-ui.table-cell>
                                        <x-ui.table-cell class="text-sm">{{ collect([$line->level, $line->location])->filter()->implode(' · ') ?: '—' }}</x-ui.table-cell>
                                        @foreach (['nos', 'length', 'width', 'height'] as $dimension)
                                            <x-ui.table-cell class="text-end tabular-nums">{{ $line->quantity_is_manual ? '' : $qty($line->{$dimension}) }}</x-ui.table-cell>
                                        @endforeach
                                        <x-ui.table-cell class="text-end tabular-nums">{{ $line->deduction ? '−' : '' }}{{ $qty($line->quantity) }}</x-ui.table-cell>
                                        <x-ui.table-cell>{{ $line->unit->symbol }}</x-ui.table-cell>
                                        <x-ui.table-cell class="text-end tabular-nums">{{ \App\Support\Money::formatRate($line->rate) }}</x-ui.table-cell>
                                        <x-ui.table-cell class="text-end tabular-nums whitespace-nowrap">{{ $money($line->amount) }}</x-ui.table-cell>
                                    </x-ui.table-row>
                                @endforeach
                            </x-ui.table-body>
                        </x-ui.table>
                    </div>
                    <x-ui.item-group class="gap-2 md:hidden">
                        @foreach ($lines as $line)
                            <x-ui.item variant="outline" class="min-h-16 py-2" wire:key="m-line-{{ $line->id }}">
                                <x-ui.item-content class="min-w-0">
                                    <x-ui.item-title class="text-base"><span class="truncate">{{ $line->description }}</span></x-ui.item-title>
                                    <x-ui.item-description class="text-sm tabular-nums">
                                        {{ $line->line_no }} ·
                                        @unless ($line->quantity_is_manual)
                                            {{ collect(['nos', 'length', 'width', 'height'])->map(fn ($field) => $line->{$field})->filter(fn ($value) => $value !== null)->map($qty)->implode(' × ') }} =
                                        @endunless
                                        {{ $qty($line->quantity) }} {{ $line->unit->symbol }}
                                    </x-ui.item-description>
                                </x-ui.item-content>
                                <span @class(['shrink-0 text-sm tabular-nums', 'text-destructive' => $line->deduction])>{{ $money($line->amount) }}</span>
                            </x-ui.item>
                        @endforeach
                    </x-ui.item-group>
                </x-ui.card>
            @endforeach
            @if ($groups->isEmpty())
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No lines yet.') }}</p>
            @endif
            <x-ui.card class="gap-2 p-4 md:p-6">
                <div class="flex justify-between text-sm"><span>{{ __('Subtotal') }}</span><span class="tabular-nums">{{ $money($estimate->subtotal) }}</span></div>
                @if ((float) $estimate->overhead_amount != 0)<div class="flex justify-between text-sm"><span>{{ __('Overhead :pct%', ['pct' => (float) $estimate->overhead_pct]) }}</span><span class="tabular-nums">{{ $money($estimate->overhead_amount) }}</span></div>@endif
                @if ((float) $estimate->profit_amount != 0)<div class="flex justify-between text-sm"><span>{{ __('Profit :pct%', ['pct' => (float) $estimate->profit_pct]) }}</span><span class="tabular-nums">{{ $money($estimate->profit_amount) }}</span></div>@endif
                @if ((float) $estimate->vat_amount != 0)<div class="flex justify-between text-sm"><span>{{ __('VAT :pct%', ['pct' => (float) $estimate->vat_pct]) }}</span><span class="tabular-nums">{{ $money($estimate->vat_amount) }}</span></div>@endif
                <div class="flex justify-between border-t pt-2 text-base font-semibold"><span>{{ __('Total') }}</span><span class="tabular-nums">{{ \App\Support\Money::format($estimate->total_amount) }}</span></div>
            </x-ui.card>
        @elseif ($tab === 'materials')
            <x-ui.item-group class="gap-2">
                @forelse ($estimate->materialLines as $line)
                    <x-ui.item variant="outline" class="min-h-16 py-2" wire:key="material-{{ $line->id }}">
                        <x-ui.item-content class="min-w-0">
                            <x-ui.item-title class="text-base"><span class="truncate">{{ $line->displayName() }}</span></x-ui.item-title>
                            <x-ui.item-description class="text-sm tabular-nums">
                                {{ $qty($line->estimated_qty) }} {{ $line->unit->symbol }}@if ($line->wastage_pct) + {{ (float) $line->wastage_pct }}% = {{ $qty($line->total_qty) }} {{ $line->unit->symbol }}@endif
                                @if ($line->purpose) · {{ $line->purpose }}@endif
                            </x-ui.item-description>
                        </x-ui.item-content>
                        <span class="shrink-0 text-sm tabular-nums">{{ $line->rate !== null ? $money($line->amount) : '—' }}</span>
                    </x-ui.item>
                @empty
                    <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No material lines.') }}</p>
                @endforelse
            </x-ui.item-group>
        @elseif ($tab === 'history')
            <x-ui.card class="p-4 md:p-6">
                <x-ui.description-list>
                    <x-ui.description-item :term="__('Date')">{{ $estimate->estimate_date->format('d-M-Y') }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Prepared by')">{{ $estimate->preparer->full_name }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Checked by')">{{ $estimate->checker?->full_name ?? '—' }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Approved')">{{ $estimate->approver ? $estimate->approver->name.' · '.$estimate->approved_at?->format('d-M-Y') : '—' }}</x-ui.description-item>
                    <x-ui.description-item :term="__('Site address')">{{ $estimate->site_address ?: '—' }}</x-ui.description-item>
                    @if ($estimate->revision_purpose)
                        <x-ui.description-item :term="__('Purpose of revision')">{{ $estimate->revision_purpose }}</x-ui.description-item>
                    @endif
                    @if ($estimate->notes)
                        <x-ui.description-item :term="__('Notes')">{{ $estimate->notes }}</x-ui.description-item>
                    @endif
                </x-ui.description-list>
            </x-ui.card>
            <x-ui.card class="p-4 md:p-6">
                <h2 class="text-base font-semibold">{{ __('Status history') }}</h2>
                <ul class="flex flex-col gap-2 text-sm">
                    @foreach ($statusHistory as $entry)
                        <li class="flex flex-wrap gap-x-2" wire:key="history-{{ $entry->id }}">
                            <span class="tabular-nums text-muted-foreground">{{ $entry->changed_at->format('d-M-Y h:i A') }}</span>
                            <span class="font-medium">{{ $entry->status->name }}</span>
                            @if ($entry->changer)<span class="text-muted-foreground">{{ $entry->changer->name }}</span>@endif
                            @if ($entry->note)<span class="w-full text-muted-foreground">{{ $entry->note }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
            <livewire:foundation.history :model="$estimate" :key="'history-'.$estimate->id" />
        @elseif ($tab === 'documents')
            <livewire:foundation.attachments :model="$estimate" :key="'attachments-'.$estimate->id" />
        @elseif ($tab === 'notes')
            <livewire:foundation.notes :model="$estimate" :key="'notes-'.$estimate->id" />
        @endif
    </div>

    @if ($primary)
        <div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 border-t bg-background px-4 py-3 md:hidden">
            @if (isset($primary['href']))
                <x-ui.button class="h-11 w-full" :href="$primary['href']" wire:navigate><x-dynamic-component :component="'lucide-'.$primary['icon']" /> {{ $primary['label'] }}</x-ui.button>
            @else
                <x-ui.button class="h-11 w-full" x-on:click="{{ $primary['click'] }}"><x-dynamic-component :component="'lucide-'.$primary['icon']" /> {{ $primary['label'] }}</x-ui.button>
            @endif
        </div>
    @endif

    @include('livewire.estimation.partials.action-sheet', ['actions' => $actions, 'sheet' => 'estimate-actions', 'title' => __('Estimate actions'), 'description' => $estimate->estimate_number.' · '.$estimate->title])

    <x-shell.sheet id="estimate-reject" :title="__('Reject estimate')" :description="__('Tell the preparer what to change.')">
        <x-ui.field>
            <x-ui.field-label for="reject-note">{{ __('Note') }} *</x-ui.field-label>
            <x-ui.textarea id="reject-note" wire:model="rejectNote" rows="3" class="text-base md:text-sm" />
            <x-ui.field-error :messages="$errors->get('rejectNote')" />
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-estimate-reject')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button variant="destructive" wire:click="reject">{{ __('Reject') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    <x-shell.sheet id="estimate-revise" :title="__('Revise estimate')" :description="__('A new draft revision copies all lines. This revision stays approved until the new one is.')">
        <x-ui.field>
            <x-ui.field-label for="revise-purpose">{{ __('Purpose of the revision') }} *</x-ui.field-label>
            <x-ui.textarea id="revise-purpose" wire:model="revisePurpose" rows="3" class="text-base md:text-sm" />
            <x-ui.field-error :messages="$errors->get('revisePurpose')" />
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-estimate-revise')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button wire:click="revise">{{ __('Start revision') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    <x-shell.sheet id="estimate-print" :title="__('Print')" :description="__('Choose a layout. It opens in a new tab ready to print or save as PDF.')">
        <div class="flex flex-col gap-2 pb-4">
            @if ($estimate->hasWorkLines())
                <x-ui.button variant="outline" class="h-11 justify-start" :href="route('estimation.estimates.print', ['estimate' => $estimate, 'layout' => 'measurement'])" target="_blank"><x-lucide-ruler /> {{ __('Measurement sheet') }}</x-ui.button>
                <x-ui.button variant="outline" class="h-11 justify-start" :href="route('estimation.estimates.print', ['estimate' => $estimate, 'layout' => 'abstract'])" target="_blank"><x-lucide-calculator /> {{ __('Abstract of cost') }}</x-ui.button>
            @endif
            @if ($estimate->hasMaterialLines())
                <x-ui.button variant="outline" class="h-11 justify-start" :href="route('estimation.estimates.print', ['estimate' => $estimate, 'layout' => 'materials'])" target="_blank"><x-lucide-boxes /> {{ __('Material statement') }}</x-ui.button>
            @endif
        </div>
    </x-shell.sheet>

    @can('delete', $estimate)
        <x-shell.confirm id="estimate-delete" :title="__('Delete estimate?')" :description="__('The draft is removed. Its number is not reused.')">
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-estimate-delete')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="destructive" wire:click="delete">{{ __('Delete') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.confirm>
    @endcan
</div>
