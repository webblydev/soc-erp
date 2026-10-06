{{-- Every sheet needs a visible title and a description saying what it is for. --}}
@props(['id', 'title', 'description'])

<x-ui.sheet :id="$id" {{ $attributes }}>
    <x-ui.sheet-content side="right"
        class="sm:max-w-md max-md:inset-x-0 max-md:top-auto max-md:bottom-0 max-md:h-auto max-md:max-h-[90dvh] max-md:w-full max-md:max-w-none max-md:rounded-t-2xl max-md:border-l-0 max-md:border-t">
        <x-ui.sheet-header>
            <x-ui.sheet-title>{{ $title }}</x-ui.sheet-title>
            <x-ui.sheet-description>{{ $description }}</x-ui.sheet-description>
        </x-ui.sheet-header>

        <div class="flex flex-1 flex-col gap-4 overflow-y-auto px-4">{{ $slot }}</div>

        @isset($footer)
            <x-ui.sheet-footer class="flex-row gap-2 pb-[calc(1rem+env(safe-area-inset-bottom))] [&>*]:h-11 [&>*]:flex-1 md:[&>*]:h-9 md:[&>*]:flex-none">
                {{ $footer }}
            </x-ui.sheet-footer>
        @endisset
    </x-ui.sheet-content>
</x-ui.sheet>
