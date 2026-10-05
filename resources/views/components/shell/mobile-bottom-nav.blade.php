@php
    $navigation = app(\App\Modules\Foundation\Services\Navigation::class);
    $user = auth()->user();
    $primary = $navigation->primaryMobile($user);
    $groups = $navigation->for($user);
@endphp

<div data-test="mobile-bottom-nav" class="fixed inset-x-0 bottom-0 z-40 border-t bg-background pb-[env(safe-area-inset-bottom)] md:hidden">
    <x-ui.bottom-navigation class="border-t-0">
        <x-ui.bottom-navigation-item icon="house" :label="__('Home')" :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate class="active:bg-accent" />

        @foreach ($primary as $item)
            <x-ui.bottom-navigation-item :icon="$item['icon']" :label="__($item['label'])" :href="$item['url']" :active="$item['active']" wire:navigate class="active:bg-accent" />
        @endforeach

        @if (count($primary) < \App\Modules\Foundation\Services\Navigation::MOBILE_PRIMARY_LIMIT)
            <x-ui.bottom-navigation-item icon="circle-user" :label="__('Profile')" :href="route('profile.edit')" :active="request()->routeIs('profile.*', 'security.*')" wire:navigate class="active:bg-accent" />
        @endif

        <x-ui.drawer class="flex flex-1">
            <x-ui.drawer-trigger class="flex flex-1">
                <x-ui.bottom-navigation-item icon="ellipsis" :label="__('More')" :href="null" class="active:bg-accent" />
            </x-ui.drawer-trigger>

            <x-ui.drawer-content>
                <x-ui.drawer-header>
                    <x-ui.drawer-title>{{ __('More') }}</x-ui.drawer-title>
                    <x-ui.drawer-description>{{ $user->name }} · {{ $user->email ?? $user->username }}</x-ui.drawer-description>
                </x-ui.drawer-header>

                <div class="flex flex-col gap-4 overflow-y-auto px-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                    @foreach ($groups as $group)
                        <x-ui.item-group>
                            <p class="px-1 text-sm font-medium text-muted-foreground">{{ __($group['label']) }}</p>
                            @foreach ($group['items'] as $item)
                                <x-ui.item size="sm" :href="$item['url']" wire:navigate class="min-h-11 active:bg-accent">
                                    <x-dynamic-component :component="'lucide-'.$item['icon']" class="size-5" />
                                    <span class="flex-1 text-sm">{{ __($item['label']) }}</span>
                                    <x-lucide-chevron-right class="size-4 text-muted-foreground" />
                                </x-ui.item>
                            @endforeach
                        </x-ui.item-group>
                    @endforeach

                    <x-ui.item-group>
                        <x-ui.item size="sm" :href="route('profile.edit')" wire:navigate class="min-h-11 active:bg-accent">
                            <x-lucide-settings class="size-5" />
                            <span class="flex-1 text-sm">{{ __('Settings') }}</span>
                            <x-lucide-chevron-right class="size-4 text-muted-foreground" />
                        </x-ui.item>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-ui.button type="submit" variant="ghost" class="h-11 w-full justify-start gap-4 px-4 text-sm font-normal">
                                <x-lucide-log-out class="size-5" />
                                {{ __('Log out') }}
                            </x-ui.button>
                        </form>
                    </x-ui.item-group>
                </div>
            </x-ui.drawer-content>
        </x-ui.drawer>
    </x-ui.bottom-navigation>
</div>
