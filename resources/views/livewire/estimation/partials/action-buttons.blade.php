{{--
    Desktop action buttons of a detail page. Each action: label, icon, and href (with optional
    external / modal) or click (Alpine expression); primary and destructive pick the variant.
--}}
<div class="hidden flex-wrap justify-end gap-2 md:flex">
    @foreach ($actions as $action)
        @php($variant = ($action['primary'] ?? false) ? 'default' : (($action['destructive'] ?? false) ? 'destructive' : 'outline'))
        @if (isset($action['href']) && ($action['external'] ?? false))
            <x-ui.button size="sm" :$variant :href="$action['href']" target="_blank"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
        @elseif (isset($action['href']))
            <x-ui.button size="sm" :$variant :href="$action['href']" wire:navigate :data-detail-modal="($action['modal'] ?? false) ?: null"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
        @else
            <x-ui.button size="sm" :$variant x-on:click="{{ $action['click'] }}"><x-dynamic-component :component="'lucide-'.$action['icon']" /> {{ $action['label'] }}</x-ui.button>
        @endif
    @endforeach
</div>
