<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-background text-foreground antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex size-9 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-foreground" />
                    </span>
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>

                <x-ui.card>
                    <x-ui.card-content>
                        {{ $slot }}
                    </x-ui.card-content>
                </x-ui.card>
            </div>
        </div>

        @persist('toast')
            <x-ui.sonner />
        @endpersist
        <x-ui.sonner-flash />
    </body>
</html>
