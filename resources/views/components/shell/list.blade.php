@props([
    'searchPlaceholder' => __('Search'),
    'createUrl' => null,
    'createLabel' => __('New'),
    'exportable' => false,
    'hasMore' => false,
    'activeFilters' => 0,
    'filterColumns' => 1,
])

@php
    // From md the filters popover can lay its fields out in 2 or 3 columns; the mobile drawer stays one column.
    [$filterWidth, $filterLayout] = match ((int) $filterColumns) {
        3 => ['w-[min(60rem,calc(100vw-2rem))]', 'grid grid-cols-2 items-start lg:grid-cols-3'],
        2 => ['w-[min(40rem,calc(100vw-2rem))]', 'grid grid-cols-2 items-start'],
        default => ['w-80', 'flex flex-col'],
    };
@endphp

<div class="flex flex-col gap-4">
    {{-- Desktop toolbar and table --}}
    <div class="hidden flex-wrap items-center gap-2 md:flex">
        <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="$searchPlaceholder" class="max-w-xs" />

        @isset($filters)
            <x-ui.popover>
                <x-ui.popover-trigger>
                    <x-ui.button variant="outline">
                        <x-lucide-list-filter />
                        {{ __('Filters') }}
                        @if ($activeFilters > 0)
                            <x-ui.badge variant="secondary">{{ $activeFilters }}</x-ui.badge>
                        @endif
                    </x-ui.button>
                </x-ui.popover-trigger>
                <x-ui.popover-content align="start" :class="$filterWidth.' max-h-[80dvh] overflow-y-auto'">
                    <div class="gap-4 {{ $filterLayout }}">
                        {{ $filters }}
                        <x-ui.button variant="ghost" size="sm" class="col-span-full justify-self-start" wire:click="clearFilters">{{ __('Clear filters') }}</x-ui.button>
                    </div>
                </x-ui.popover-content>
            </x-ui.popover>
        @endisset

        <div class="ms-auto flex flex-wrap items-center justify-end gap-2">
            @isset($actions)
                {{ $actions }}
            @endisset
            @if ($exportable)
                <x-ui.button variant="outline" wire:click="export">
                    <x-lucide-download />
                    {{ __('Export') }}
                </x-ui.button>
            @endif
            @if ($createUrl)
                <x-ui.button :href="$createUrl" wire:navigate data-detail-modal>
                    <x-lucide-plus />
                    {{ $createLabel }}
                </x-ui.button>
            @endif
        </div>
    </div>

    <div class="hidden min-w-0 md:block" x-data>{{ $desktop }}</div>

    {{-- Mobile search, filter sheet and rows --}}
    <div class="flex flex-col gap-3 md:hidden">
        <div class="flex items-center gap-2">
            <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="$searchPlaceholder" class="h-11 min-w-0 flex-1 text-base" />

            @isset($filters)
                <x-ui.drawer>
                    <x-ui.drawer-trigger>
                        <x-ui.button variant="outline" size="icon" class="relative size-11" :aria-label="__('Filters')">
                            <x-lucide-list-filter class="size-5" />
                            @if ($activeFilters > 0)
                                <span class="absolute -top-1 -end-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-sm text-primary-foreground">{{ $activeFilters }}</span>
                            @endif
                        </x-ui.button>
                    </x-ui.drawer-trigger>
                    <x-ui.drawer-content>
                        <x-ui.drawer-header>
                            <x-ui.drawer-title>{{ __('Filters') }}</x-ui.drawer-title>
                            <x-ui.drawer-description>{{ __('Narrow down the list.') }}</x-ui.drawer-description>
                        </x-ui.drawer-header>
                        <div class="flex flex-col gap-4 overflow-y-auto px-4">{{ $filters }}</div>
                        <x-ui.drawer-footer class="flex-row gap-2 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                            <x-ui.button variant="outline" class="h-11 flex-1" wire:click="clearFilters">{{ __('Clear') }}</x-ui.button>
                            <x-ui.drawer-close class="flex-1">
                                <x-ui.button class="h-11 w-full">{{ __('Done') }}</x-ui.button>
                            </x-ui.drawer-close>
                        </x-ui.drawer-footer>
                    </x-ui.drawer-content>
                </x-ui.drawer>
            @endisset

            @isset($actions)
                {{ $actions }}
            @endisset

            @if ($exportable)
                <x-ui.button variant="outline" size="icon" class="size-11" wire:click="export" :aria-label="__('Export')">
                    <x-lucide-download class="size-5" />
                </x-ui.button>
            @endif
        </div>

        <x-ui.item-group class="gap-2">{{ $mobile }}</x-ui.item-group>

        @if ($hasMore)
            <x-ui.infinite-scroll x-on:load-more.prevent="$wire.loadMore().then(() => $el.dispatchEvent(new CustomEvent('load-more-done', { detail: { done: false } })))" />
        @endif
    </div>

    @if ($createUrl)
        <a href="{{ $createUrl }}" wire:navigate aria-label="{{ $createLabel }}"
           class="fixed end-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg active:scale-95 md:hidden">
            <x-lucide-plus class="size-6" />
        </a>
    @endif
</div>
