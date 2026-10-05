@props(['key', 'label', 'sort' => '', 'direction' => 'asc'])

<button type="button" wire:click="sortBy('{{ $key }}')" class="inline-flex items-center gap-1 font-medium hover:text-foreground">
    {{ $label }}
    @if ($sort === $key)
        <x-dynamic-component :component="$direction === 'desc' ? 'lucide-arrow-down' : 'lucide-arrow-up'" class="size-3.5" />
    @else
        <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
    @endif
</button>
