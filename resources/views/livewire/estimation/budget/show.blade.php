@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $money = fn ($value) => \App\Support\Money::format($value, false);
@endphp

<div class="flex flex-col gap-6 pb-24 md:pb-0">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 flex-col gap-1">
            <span class="font-mono text-sm text-muted-foreground">{{ $project->project_number }}</span>
            <h1 class="text-xl font-semibold tracking-tight md:text-2xl">{{ __('Budget') }}</h1>
            <span class="text-sm text-muted-foreground">{{ $project->name }}</span>
        </div>
        @if ($canManage)
            <x-ui.button size="sm" class="hidden md:inline-flex" x-on:click="$dispatch('open-sheet-budget-edit')"><x-lucide-pencil /> {{ __('Edit budget') }}</x-ui.button>
        @endif
    </div>

    <x-ui.card class="gap-3 p-4 md:p-6">
        @include('livewire.estimation.budget.summary', ['summary' => $summary, 'hasCostSources' => $hasCostSources])
    </x-ui.card>

    <x-ui.card class="gap-3 p-4 md:p-6">
        <h2 class="text-base font-semibold">{{ __('Revisions') }}</h2>
        <ul class="flex flex-col gap-2 text-sm">
            @forelse ($revisions as $revision)
                <li class="flex flex-wrap gap-x-2" wire:key="revision-{{ $revision->id }}">
                    <span class="font-medium">{{ __('Rev. :n', ['n' => $revision->revision_no]) }}</span>
                    <span class="tabular-nums">{{ $money($revision->old_total) }} → {{ $money($revision->new_total) }}</span>
                    <span class="text-muted-foreground">{{ $revision->approver?->name }} · {{ $revision->approved_at->format('d-M-Y') }}</span>
                    @if ($revision->reason)<span class="w-full text-muted-foreground">{{ $revision->reason }}</span>@endif
                </li>
            @empty
                <li class="text-muted-foreground">{{ __('No changes yet.') }}</li>
            @endforelse
        </ul>
    </x-ui.card>

    @if ($canManage)
        <div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 border-t bg-background px-4 py-3 md:hidden">
            <x-ui.button class="h-11 w-full" x-on:click="$dispatch('open-sheet-budget-edit')"><x-lucide-pencil /> {{ __('Edit budget') }}</x-ui.button>
        </div>

        <x-shell.sheet id="budget-edit" :title="__('Edit budget')" :description="__('Lines typed by hand. Lines built from estimates change when the estimate is revised.')">
            @foreach ($lines as $i => $line)
                <div class="flex flex-col gap-2 rounded-md border p-3" wire:key="budget-line-{{ $i }}">
                    <div class="flex gap-2">
                        <x-ui.select native wire:model="lines.{{ $i }}.cost_category_id" class="{{ $input }} flex-1" :aria-label="__('Cost category')">
                            <option value="">{{ __('Cost category…') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.button type="button" variant="ghost" size="icon" class="size-11 md:size-9" wire:click="removeLine({{ $i }})" :aria-label="__('Remove line')"><x-lucide-trash-2 /></x-ui.button>
                    </div>
                    <x-ui.input wire:model="lines.{{ $i }}.description" :placeholder="__('Description')" class="{{ $input }}" :aria-label="__('Description')" />
                    <div class="grid grid-cols-3 gap-2">
                        <x-ui.input wire:model="lines.{{ $i }}.budget_qty" inputmode="decimal" :placeholder="__('Qty')" class="{{ $input }} text-end tabular-nums" :aria-label="__('Quantity')" />
                        <x-ui.select native wire:model="lines.{{ $i }}.unit_id" class="{{ $input }}" :aria-label="__('Unit')">
                            <option value="">—</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->symbol }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input wire:model="lines.{{ $i }}.budget_amount" inputmode="decimal" :placeholder="__('Amount')" class="{{ $input }} text-end tabular-nums" :aria-label="__('Amount')" />
                    </div>
                    <x-ui.field-error :messages="collect($errors->getMessages())->filter(fn ($messages, $field) => str_starts_with($field, 'lines.'.$i.'.'))->flatten()->all()" />
                </div>
            @endforeach
            <x-ui.button type="button" variant="outline" class="h-11 self-start md:h-9" wire:click="addLine"><x-lucide-plus /> {{ __('Add line') }}</x-ui.button>
            <x-ui.field>
                <x-ui.field-label for="budget-reason">{{ __('Reason') }}{{ $needsReason ? ' *' : '' }}</x-ui.field-label>
                <x-ui.textarea id="budget-reason" wire:model="reason" rows="2" class="text-base md:text-sm" />
                <x-ui.field-description>{{ __('Needed when the approved budget total changes (ES-BR-05).') }}</x-ui.field-description>
                <x-ui.field-error :messages="$errors->get('reason')" />
            </x-ui.field>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-budget-edit')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button wire:click="save">{{ __('Save budget') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endif
</div>
