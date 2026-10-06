{{--
    Create/edit screen. Below md it is the same full-screen form as x-shell.form-page (sections
    flow as one column, sticky bottom action bar). From md it gets a page header with the actions,
    and from lg the sections sit in a main column beside an optional sticky `aside` column.

    asidePosition  'end' (right) or 'start' (left). The aside is rendered in that DOM position,
                   so on mobile it also comes after or before the main sections.
--}}
@props(['heading' => null, 'description' => null, 'cancelUrl' => null, 'submitLabel' => __('Save'), 'asidePosition' => 'end'])

@php($hasAside = isset($aside) && $aside->isNotEmpty())

<form {{ $attributes->merge(['class' => 'mx-auto flex w-full max-w-6xl flex-col gap-6 pb-28 md:pb-0']) }}>
    <div data-test="form-screen-header" class="hidden items-start justify-between gap-4 md:flex">
        <div class="flex min-w-0 flex-col gap-1">
            <h1 class="truncate text-2xl font-semibold tracking-tight">{{ $heading }}</h1>
            @if ($description)
                <p class="text-sm text-muted-foreground">{{ $description }}</p>
            @endif
        </div>
        <div class="flex shrink-0 gap-2">
            @if ($cancelUrl)
                <x-ui.button variant="outline" :href="$cancelUrl" wire:navigate>{{ __('Cancel') }}</x-ui.button>
            @endif
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                <x-lucide-loader-circle class="animate-spin" wire:loading wire:target="save" />
                {{ $submitLabel }}
            </x-ui.button>
        </div>
    </div>

    @isset($alerts)
        {{ $alerts }}
    @endisset

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
        @if ($hasAside && $asidePosition === 'start')
            <div class="flex min-w-0 flex-col gap-6 lg:sticky lg:top-6">{{ $aside }}</div>
        @endif

        <div class="flex min-w-0 flex-col gap-6 {{ $hasAside ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            {{ $slot }}
        </div>

        @if ($hasAside && $asidePosition === 'end')
            <div class="flex min-w-0 flex-col gap-6 lg:sticky lg:top-6">{{ $aside }}</div>
        @endif
    </div>

    <div data-test="mobile-action-bar" class="fixed inset-x-0 bottom-0 z-40 flex gap-2 border-t bg-background px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] md:hidden">
        @if ($cancelUrl)
            <x-ui.button variant="outline" class="h-11 flex-1" :href="$cancelUrl" wire:navigate>{{ __('Cancel') }}</x-ui.button>
        @endif
        <x-ui.button type="submit" class="h-11 flex-1">{{ $submitLabel }}</x-ui.button>
    </div>
</form>
