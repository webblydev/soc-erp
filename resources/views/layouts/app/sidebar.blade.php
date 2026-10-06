@props(['title' => null, 'back' => null, 'bottomNav' => true])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-background text-foreground antialiased">
        <x-ui.top-progress />

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
                    <x-shell.sidebar-nav />
                </x-ui.sidebar-content>

                <x-ui.sidebar-footer>
                    <x-desktop-user-menu />
                </x-ui.sidebar-footer>

                <x-ui.sidebar-rail />
            </x-ui.sidebar>

            <x-ui.sidebar-inset class="pt-[calc(3.5rem+env(safe-area-inset-top))] {{ $bottomNav ? 'pb-[calc(4rem+env(safe-area-inset-bottom))]' : '' }} md:pt-0 md:pb-0">
                @if (session()->has(\App\Http\Middleware\HandleImpersonation::SESSION_KEY))
                    <x-shell.impersonation-banner />
                @endif
                <header class="hidden h-14 shrink-0 items-center gap-2 border-b px-4 md:flex">
                    <x-ui.sidebar-trigger class="-ms-1" />
                    <x-ui.separator orientation="vertical" class="me-2 h-4!" />

                    @isset($breadcrumbs)
                        {{ $breadcrumbs }}
                    @else
                        <span class="text-sm font-medium">{{ $title }}</span>
                    @endisset

                    <div class="ms-auto flex items-center gap-1">
                        <x-ui.button variant="ghost" size="icon" disabled :aria-label="__('Search')">
                            <x-lucide-search />
                        </x-ui.button>
                        @isset($quickCreate)
                            {{ $quickCreate }}
                        @endisset
                        <x-ui.button variant="ghost" size="icon" disabled :aria-label="__('Notifications')">
                            <x-lucide-bell />
                        </x-ui.button>
                    </div>
                </header>

                <x-shell.mobile-top-bar :title="$title" :back="$back" :actions="$actions ?? null" />

                {{ $slot }}
            </x-ui.sidebar-inset>
        </x-ui.sidebar-provider>

        @if ($bottomNav)
            <x-shell.mobile-bottom-nav />
        @endif

        @persist('toast')
            <x-ui.sonner />
        @endpersist
        <x-ui.sonner-flash />
    </body>
</html>
