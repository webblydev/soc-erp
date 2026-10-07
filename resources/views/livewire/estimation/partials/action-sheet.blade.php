{{-- The same actions as a bottom sheet for the mobile top-bar ⋮ button ($sheet, $title, $description). --}}
<x-shell.sheet :id="$sheet" :title="$title" :description="$description">
    <div class="flex flex-col gap-2 pb-4">
        @forelse ($actions as $action)
            @php($tone = ($action['destructive'] ?? false) ? 'text-destructive' : '')
            @if (isset($action['href']) && ($action['external'] ?? false))
                <x-ui.button class="h-11 justify-start {{ $tone }}" variant="outline" :href="$action['href']" target="_blank"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
            @elseif (isset($action['href']))
                <x-ui.button class="h-11 justify-start {{ $tone }}" variant="outline" :href="$action['href']" wire:navigate><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
            @else
                <x-ui.button class="h-11 justify-start {{ $tone }}" variant="outline" x-on:click="$dispatch('close-sheet-{{ $sheet }}'); {{ $action['click'] }}"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
            @endif
        @empty
            <p class="text-sm text-muted-foreground">{{ __('No actions are available to you.') }}</p>
        @endforelse
    </div>
</x-shell.sheet>
