@php($groups = app(\App\Modules\Foundation\Services\Navigation::class)->for(auth()->user()))

<x-ui.sidebar-group>
    <x-ui.sidebar-group-content>
        <x-ui.sidebar-menu>
            <x-ui.sidebar-menu-item>
                <x-ui.sidebar-menu-button :href="route('dashboard')" :is-active="request()->routeIs('dashboard')" :tooltip="__('Home')" wire:navigate>
                    <x-lucide-house />
                    <span>{{ __('Home') }}</span>
                </x-ui.sidebar-menu-button>
            </x-ui.sidebar-menu-item>
        </x-ui.sidebar-menu>
    </x-ui.sidebar-group-content>
</x-ui.sidebar-group>

@foreach ($groups as $group)
    <x-ui.sidebar-group>
        <x-ui.sidebar-group-label>{{ __($group['label']) }}</x-ui.sidebar-group-label>
        <x-ui.sidebar-group-content>
            <x-ui.sidebar-menu>
                @foreach ($group['items'] as $item)
                    <x-ui.sidebar-menu-item>
                        <x-ui.sidebar-menu-button :href="$item['url']" :is-active="$item['active']" :tooltip="__($item['label'])" wire:navigate>
                            <x-dynamic-component :component="'lucide-'.$item['icon']" />
                            <span>{{ __($item['label']) }}</span>
                        </x-ui.sidebar-menu-button>
                    </x-ui.sidebar-menu-item>
                @endforeach
            </x-ui.sidebar-menu>
        </x-ui.sidebar-group-content>
    </x-ui.sidebar-group>
@endforeach
