<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-background text-foreground antialiased">
        <x-ui.sidebar-provider>
            <x-ui.sidebar collapsible="icon">
                <x-ui.sidebar-header>
                    <x-ui.sidebar-menu>
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button size="lg" :href="route('dashboard')" wire:navigate>
                                <x-app-logo />
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    </x-ui.sidebar-menu>
                </x-ui.sidebar-header>

                <x-ui.sidebar-content>
                    <x-ui.sidebar-group>
                        <x-ui.sidebar-group-label>{{ __('Platform') }}</x-ui.sidebar-group-label>
                        <x-ui.sidebar-group-content>
                            <x-ui.sidebar-menu>
                                <x-ui.sidebar-menu-item>
                                    <x-ui.sidebar-menu-button :href="route('dashboard')" :is-active="request()->routeIs('dashboard')" :tooltip="__('Dashboard')" wire:navigate>
                                        <x-lucide-layout-grid />
                                        <span>{{ __('Dashboard') }}</span>
                                    </x-ui.sidebar-menu-button>
                                </x-ui.sidebar-menu-item>
                            </x-ui.sidebar-menu>
                        </x-ui.sidebar-group-content>
                    </x-ui.sidebar-group>
                </x-ui.sidebar-content>

                <x-ui.sidebar-footer>
                    <x-desktop-user-menu />
                </x-ui.sidebar-footer>

                <x-ui.sidebar-rail />
            </x-ui.sidebar>

            <x-ui.sidebar-inset>
                <header class="flex h-14 shrink-0 items-center gap-2 border-b px-4">
                    <x-ui.sidebar-trigger class="-ms-1" />
                    <x-ui.separator orientation="vertical" class="me-2 h-4!" />
                    <span class="text-sm font-medium">{{ $title ?? '' }}</span>
                </header>

                {{ $slot }}
            </x-ui.sidebar-inset>
        </x-ui.sidebar-provider>

        @persist('toast')
            <x-ui.sonner />
        @endpersist
        <x-ui.sonner-flash />
    </body>
</html>
