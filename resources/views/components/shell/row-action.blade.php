{{--
    One icon action in a desktop table's Actions column. The label is the tooltip and the
    accessible name. Other attributes (wire:click, disabled …) go to the button. A wire:confirm
    text asks in the page-wide x-shell.confirm-action dialog before running wire:click.
--}}
@props(['icon', 'label', 'href' => null, 'destructive' => false])

@php
    if ($attributes->has('wire:confirm') && $attributes->has('wire:click')) {
        $confirm = ['title' => $attributes->get('wire:confirm'), 'confirmLabel' => $label];
        $attributes = $attributes->except(['wire:confirm', 'wire:click'])->merge([
            'x-on:click' => '$dispatch(\'confirm-action\', { ...'.Js::from($confirm).', confirm: () => $wire.'.$attributes->get('wire:click').' })',
        ]);
    }
@endphp

<x-ui.tooltip>
    <x-ui.tooltip-trigger>
        <x-ui.button variant="ghost" size="icon" :href="$href" :aria-label="$label"
            {{ $attributes->merge($href ? ['wire:navigate' => true] : [])->twMerge('size-8 '.($destructive ? 'text-destructive hover:bg-destructive/10 hover:text-destructive' : '')) }}>
            <x-dynamic-component :component="'lucide-'.$icon" />
        </x-ui.button>
    </x-ui.tooltip-trigger>
    <x-ui.tooltip-content>{{ $label }}</x-ui.tooltip-content>
</x-ui.tooltip>
