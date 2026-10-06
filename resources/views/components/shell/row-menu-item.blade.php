{{-- One icon action in a row's Action cell; the slot text is its tooltip and accessible name. Other attributes (wire:click, wire:confirm …) go to the button. --}}
@props(['icon', 'href' => null, 'destructive' => false])

<x-shell.row-action :icon="$icon" :href="$href" :destructive="$destructive" :label="html_entity_decode(trim(strip_tags((string) $slot)), ENT_QUOTES)" {{ $attributes }} />
