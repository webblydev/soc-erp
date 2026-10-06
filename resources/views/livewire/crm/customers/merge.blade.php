@php
    $labels = ['leads' => __('Converted leads'), 'referred_leads' => __('Referred leads'), 'activities' => __('Activities'), 'contacts' => __('Contacts'), 'attachments' => __('Documents'), 'notes' => __('Notes')];
@endphp

<div>
    <x-shell.form-screen wire:submit="merge"
        :heading="__('Merge customers')"
        :description="__('Everything on the duplicate moves to the customer you keep. The duplicate is hidden and its number stops working.')"
        :cancel-url="route('crm.customers.show', $survivor)"
        :submit-label="__('Merge customers')">

        <x-shell.form-section :title="__('Customers')">
            <div class="grid grid-cols-1 items-center gap-3 md:grid-cols-[1fr_auto_1fr]">
                <x-ui.card class="gap-1 p-4">
                    <span class="text-sm text-muted-foreground">{{ __('Keep') }}</span>
                    <p class="font-medium">{{ $survivor->name }}</p>
                    <p class="text-sm"><span class="font-mono">{{ $survivor->customer_number }}</span> · <span class="tabular-nums">{{ $survivor->phone }}</span></p>
                </x-ui.card>
                <x-ui.button type="button" variant="outline" class="h-11 justify-self-center md:h-9" wire:click="swap" :disabled="$duplicate === null">
                    <x-lucide-arrow-left-right /> {{ __('Swap') }}
                </x-ui.button>
                <x-ui.card class="gap-1 p-4">
                    <span class="text-sm text-muted-foreground">{{ __('Merge into it') }}</span>
                    @if ($duplicate)
                        <p class="font-medium">{{ $duplicate->name }}</p>
                        <p class="text-sm"><span class="font-mono">{{ $duplicate->customer_number }}</span> · <span class="tabular-nums">{{ $duplicate->phone }}</span></p>
                    @else
                        <p class="text-sm text-muted-foreground">{{ __('Search for the duplicate below.') }}</p>
                    @endif
                </x-ui.card>
            </div>

            <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Search customers by number, name or phone')" class="h-11 text-base md:h-9 md:text-sm" />
            <x-ui.item-group class="gap-2">
                @foreach ($results as $result)
                    <x-ui.item variant="outline" class="min-h-11 cursor-pointer active:bg-accent" wire:key="dup-{{ $result->id }}" wire:click="$set('duplicateId', '{{ $result->id }}')">
                        <x-ui.item-content>
                            <x-ui.item-title>{{ $result->name }}</x-ui.item-title>
                            <x-ui.item-description class="text-sm"><span class="font-mono">{{ $result->customer_number }}</span> · {{ $result->phone }}</x-ui.item-description>
                        </x-ui.item-content>
                    </x-ui.item>
                @endforeach
            </x-ui.item-group>
            <x-ui.field-error :messages="$errors->get('duplicate')" />
        </x-shell.form-section>

        @if ($preview !== [])
            <x-shell.form-section :title="__('What moves')">
                <x-ui.description-list>
                    @foreach ($labels as $key => $label)
                        <x-ui.description-item :term="$label"><span class="tabular-nums">{{ $preview[$key] ?? 0 }}</span></x-ui.description-item>
                    @endforeach
                </x-ui.description-list>
            </x-shell.form-section>
        @endif

        <x-shell.form-section :title="__('Reason')">
            <x-ui.field>
                <x-ui.field-label for="merge-reason">{{ __('Why are these the same customer?') }} *</x-ui.field-label>
                <x-ui.input id="merge-reason" wire:model="reason" class="h-11 text-base md:h-9 md:text-sm" />
                <x-ui.field-error :messages="$errors->get('reason')" />
            </x-ui.field>
        </x-shell.form-section>
    </x-shell.form-screen>
</div>
