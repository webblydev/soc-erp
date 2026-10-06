<div class="flex flex-col gap-3">
    @foreach ($definitions as $definition)
        <x-ui.card class="gap-3 py-4" wire:key="definition-{{ $definition->id }}">
            <x-ui.card-header class="flex flex-row items-start justify-between gap-2 px-4">
                <div class="min-w-0">
                    <x-ui.card-title class="text-base">{{ \Illuminate\Support\Str::headline($definition->document_type) }}</x-ui.card-title>
                    <x-ui.card-description class="font-mono text-sm">{{ $definition->format }}</x-ui.card-description>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ $definition->reset_policy === 'fiscal_year' ? __('Resets every fiscal year') : __('Never resets') }}
                        @if ($definition->scope_by) · {{ __('Separate per :scope', ['scope' => str_replace('_', ' ', $definition->scope_by)]) }} @endif
                    </p>
                </div>
                @can('admin.sequences.update')
                    <x-ui.button variant="ghost" size="icon" class="size-11 md:size-9" wire:click="editFormat({{ $definition->id }})" :aria-label="__('Edit format')"><x-lucide-pencil /></x-ui.button>
                @endcan
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-1 px-4">
                @forelse ($definition->sequences as $sequence)
                    <div class="flex min-h-11 items-center gap-2 border-t pt-1 text-sm" wire:key="counter-{{ $sequence->id }}">
                        <span class="w-24 shrink-0 font-mono text-muted-foreground">{{ $sequence->scope_key === '' ? __('global') : $sequence->scope_key }}</span>
                        <span class="flex-1 font-mono">{{ $sample($sequence->format, $sequence->next_number) }}</span>
                        @can('admin.sequences.update')
                            <x-ui.button variant="outline" size="sm" class="h-11 md:h-8" wire:click="editCounter({{ $sequence->id }})">{{ __('Next: :n', ['n' => $sequence->next_number]) }}</x-ui.button>
                        @else
                            <span class="text-muted-foreground">{{ __('Next: :n', ['n' => $sequence->next_number]) }}</span>
                        @endcan
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('Sample: :sample (no numbers issued yet)', ['sample' => $sample($definition->format, 1)]) }}</p>
                @endforelse
            </x-ui.card-content>
        </x-ui.card>
    @endforeach

    <x-shell.sheet id="sequence-format" :title="__('Edit format')" :description="__('Set how new document numbers are built.')">
        <form wire:submit="saveFormat" id="sequence-format-form" class="flex flex-col gap-3 pb-2">
            <x-ui.field>
                <x-ui.field-label for="format">{{ __('Format') }}</x-ui.field-label>
                <x-ui.input id="format" wire:model.live.debounce.300ms="format" autocapitalize="none" class="h-11 font-mono text-base md:h-9 md:text-sm" />
                <x-ui.field-description>{{ __('Tokens: {seq:N}, {yy}, {yyyy}, {bl_prefix}, {branch}') }}</x-ui.field-description>
                <x-ui.field-error :messages="$errors->get('format')" />
            </x-ui.field>
            <p class="text-sm">{{ __('Sample') }}: <span class="font-mono">{{ $sample($format, 1) }}</span></p>
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="sequence-format-form">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    <x-shell.sheet id="sequence-counter" :title="__('Change next number')" :description="$counter ? __(':type · :scope', ['type' => $counter->document_type, 'scope' => $counter->scope_key ?: __('global')]) : __('Set the next number to issue.')">
        <form wire:submit="saveCounter" id="sequence-counter-form" class="flex flex-col gap-3 pb-2">
            <x-ui.alert>
                <x-lucide-triangle-alert />
                <x-ui.alert-description>{{ __('The next number can only go up. Skipped numbers are never reused.') }}</x-ui.alert-description>
            </x-ui.alert>
            <x-ui.field>
                <x-ui.field-label for="nextNumber">{{ __('Next number') }}</x-ui.field-label>
                <x-ui.input id="nextNumber" type="number" inputmode="numeric" wire:model="nextNumber" class="h-11 text-base md:h-9 md:text-sm" />
                <x-ui.field-error :messages="$errors->get('nextNumber')" />
            </x-ui.field>
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="sequence-counter-form">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
