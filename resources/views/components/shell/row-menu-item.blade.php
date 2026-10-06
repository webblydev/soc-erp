{{-- One entry in a row's ⋮ menu. A link navigates with wire:navigate; other attributes (wire:click, wire:confirm …) go to the item. --}}
@props(['icon', 'href' => null, 'destructive' => false])

<x-ui.dropdown-menu-item :href="$href" :variant="$destructive ? 'destructive' : 'default'" {{ $attributes->merge($href ? ['wire:navigate' => true] : []) }}>
    <x-dynamic-component :component="'lucide-'.$icon" />
    {{ $slot }}
</x-ui.dropdown-menu-item>
