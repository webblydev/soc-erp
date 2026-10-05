@props(['title' => null, 'back' => null, 'actions' => null])

<header data-test="mobile-top-bar" class="fixed inset-x-0 top-0 z-40 border-b bg-background pt-[env(safe-area-inset-top)] md:hidden">
    <div class="flex h-14 items-center gap-1 px-1">
        @if ($back)
            <x-ui.button variant="ghost" size="icon" class="size-11" :href="$back" wire:navigate :aria-label="__('Back')">
                <x-lucide-arrow-left class="size-5" />
            </x-ui.button>
        @else
            <a href="{{ route('dashboard') }}" wire:navigate class="flex size-11 items-center justify-center rounded-md active:bg-accent" aria-label="{{ __('Home') }}">
                <span class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <x-app-logo-icon class="size-5 fill-current" />
                </span>
            </a>
        @endif

        <h1 class="min-w-0 flex-1 truncate px-1 text-base font-semibold">{{ $title }}</h1>

        @if ($actions)
            <div class="flex items-center gap-2">{{ $actions }}</div>
        @endif
    </div>
</header>
