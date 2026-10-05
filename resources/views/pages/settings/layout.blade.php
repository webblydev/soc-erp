<div class="flex items-start max-md:flex-col">
    <nav class="me-10 w-full pb-4 md:w-[220px]" aria-label="{{ __('Settings') }}">
        <div class="flex flex-col gap-1">
            <x-ui.button variant="ghost" class="justify-start {{ request()->routeIs('profile.edit') ? 'bg-accent text-accent-foreground' : '' }}" :href="route('profile.edit')" wire:navigate>
                {{ __('Profile') }}
            </x-ui.button>
            <x-ui.button variant="ghost" class="justify-start {{ request()->routeIs('security.edit') ? 'bg-accent text-accent-foreground' : '' }}" :href="route('security.edit')" wire:navigate>
                {{ __('Security') }}
            </x-ui.button>
        </div>
    </nav>

    <x-ui.separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <h2 class="text-lg font-semibold">{{ $heading ?? '' }}</h2>
        <x-ui.typography variant="muted">{{ $subheading ?? '' }}</x-ui.typography>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
