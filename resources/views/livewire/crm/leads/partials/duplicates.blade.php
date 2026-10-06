{{-- The duplicate matches with the three choices of spec R10; used inline (desktop) and in the sheet (mobile). --}}
@foreach ($duplicates as $match)
    <div class="flex flex-col gap-2 rounded-md border bg-background p-3 text-foreground md:flex-row md:items-center md:justify-between" wire:key="dup-{{ $match['type'] }}-{{ $match['id'] }}">
        <div class="min-w-0 text-sm">
            <p class="font-medium">{{ $match['name'] }} <span class="font-mono text-muted-foreground">{{ $match['number'] }}</span></p>
            <p class="text-muted-foreground">{{ $match['type'] === 'lead' ? __('Lead') : __('Customer') }} · {{ $match['status'] }}@if ($match['owner']) · {{ $match['owner'] }}@endif</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.button type="button" size="sm" variant="outline" class="h-11 md:h-8" :href="$duplicateUrl($match)" wire:navigate>{{ __('Open existing') }}</x-ui.button>
            @if ($match['type'] === 'customer')
                <x-ui.button type="button" size="sm" class="h-11 md:h-8" wire:click="addAsEnquiry({{ $match['id'] }})">{{ __('Add as new enquiry for this customer') }}</x-ui.button>
            @endif
        </div>
    </div>
@endforeach
<x-ui.field orientation="horizontal" class="min-h-11 items-center">
    <x-ui.switch wire:model.live="overrideDuplicates" :checked="$overrideDuplicates" />
    <x-ui.field-label class="text-foreground">{{ __('Create anyway') }}</x-ui.field-label>
</x-ui.field>
@if ($overrideDuplicates)
    <x-ui.input wire:model="duplicate_reason" :placeholder="__('Reason')" class="h-11 text-base md:h-9 md:text-sm" :aria-label="__('Reason for creating anyway')" />
@endif
