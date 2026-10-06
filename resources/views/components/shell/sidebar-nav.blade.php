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
                    @isset($item['children'])
                        {{-- Tree node. On the icon rail its children are hidden, so a click opens the sidebar first. --}}
                        <x-ui.sidebar-menu-item data-test="sidebar-tree-node" :x-data="'{ expanded: '.($item['active'] ? 'true' : 'false').' }'">
                            <x-ui.sidebar-menu-button :tooltip="__($item['label'])" x-bind:aria-expanded="expanded"
                                x-on:click="collapsed ? (toggle(), expanded = true) : (expanded = ! expanded)">
                                <x-dynamic-component :component="'lucide-'.$item['icon']" />
                                <span>{{ __($item['label']) }}</span>
                                <x-lucide-chevron-right class="ms-auto transition-transform duration-200" x-bind:class="expanded && 'rotate-90'" />
                            </x-ui.sidebar-menu-button>
                            <x-ui.sidebar-menu-sub x-show="expanded" x-collapse x-cloak>
                                @foreach ($item['children'] as $child)
                                    <x-ui.sidebar-menu-sub-item>
                                        <x-ui.sidebar-menu-sub-button :href="$child['url']" :is-active="$child['active']" wire:navigate>
                                            <span>{{ __($child['label']) }}</span>
                                        </x-ui.sidebar-menu-sub-button>
                                    </x-ui.sidebar-menu-sub-item>
                                @endforeach
                            </x-ui.sidebar-menu-sub>
                        </x-ui.sidebar-menu-item>
                    @else
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button :href="$item['url']" :is-active="$item['active']" :tooltip="__($item['label'])" wire:navigate>
                                <x-dynamic-component :component="'lucide-'.$item['icon']" />
                                <span>{{ __($item['label']) }}</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endisset
                @endforeach
            </x-ui.sidebar-menu>
        </x-ui.sidebar-group-content>
    </x-ui.sidebar-group>
@endforeach
