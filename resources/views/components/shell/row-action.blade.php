{{--
    One icon action in a desktop table's Actions column. The label is the tooltip and the
    accessible name. Other attributes (wire:click, wire:confirm, disabled …) go to the button.
--}}
@props(['icon', 'label', 'href' => null, 'destructive' => false])

<x-ui.tooltip>
    <x-ui.tooltip-trigger>
        <x-ui.button variant="ghost" size="icon" :href="$href" :aria-label="$label"
            {{ $attributes->merge($href ? ['wire:navigate' => true] : [])->twMerge('size-8 '.($destructive ? 'text-destructive hover:bg-destructive/10 hover:text-destructive' : '')) }}>
            <x-dynamic-component :component="'lucide-'.$icon" />
        </x-ui.button>
    </x-ui.tooltip-trigger>
    <x-ui.tooltip-content>{{ $label }}</x-ui.tooltip-content>
</x-ui.tooltip>
