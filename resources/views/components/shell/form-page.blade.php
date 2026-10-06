@props(['cancelUrl' => null, 'submitLabel' => __('Save'), 'aboveNav' => false])

<form {{ $attributes->merge(['class' => 'flex flex-col gap-6 '.($submitLabel ? 'pb-28 md:pb-0' : '')]) }}>
    <x-ui.card class="max-md:border-0 max-md:bg-transparent max-md:py-0 max-md:shadow-none">
        <x-ui.card-content class="flex max-w-2xl flex-col gap-6 max-md:px-0">
            {{ $slot }}
        </x-ui.card-content>
        @if ($submitLabel)
        <x-ui.card-footer class="hidden justify-end gap-2 md:flex">
            @if ($cancelUrl)
                <x-ui.button variant="outline" :href="$cancelUrl" wire:navigate>{{ __('Cancel') }}</x-ui.button>
            @endif
            <x-ui.button type="submit">{{ $submitLabel }}</x-ui.button>
        </x-ui.card-footer>
        @endif
    </x-ui.card>

    @if ($submitLabel)
    <div data-test="mobile-action-bar" class="fixed inset-x-0 {{ $aboveNav ? 'bottom-[calc(4rem+env(safe-area-inset-bottom))]' : 'bottom-0' }} z-40 flex gap-2 border-t bg-background px-4 pt-3 {{ $aboveNav ? 'pb-3' : 'pb-[calc(0.75rem+env(safe-area-inset-bottom))]' }} md:hidden">
        @if ($cancelUrl)
            <x-ui.button variant="outline" class="h-11 flex-1" :href="$cancelUrl" wire:navigate>{{ __('Cancel') }}</x-ui.button>
        @endif
        <x-ui.button type="submit" class="h-11 flex-1">{{ $submitLabel }}</x-ui.button>
    </div>
    @endif
</form>
