{{--
    A titled card inside x-shell.form-screen. Below md the card chrome and heading drop away so
    the fields read as one continuous mobile form; fields keep their own labels there.
--}}
@props(['title' => null, 'description' => null])

<x-ui.card variant="sectioned" {{ $attributes->twMerge('max-md:gap-0 max-md:rounded-none max-md:border-0 max-md:bg-transparent max-md:py-0 max-md:shadow-none') }}>
    @if ($title)
        <x-ui.card-header class="border-b max-md:hidden">
            <x-ui.card-title>{{ $title }}</x-ui.card-title>
            @if ($description)
                <x-ui.card-description>{{ $description }}</x-ui.card-description>
            @endif
            @isset($action)
                <x-ui.card-action>{{ $action }}</x-ui.card-action>
            @endisset
        </x-ui.card-header>
    @endif
    <x-ui.card-content class="flex flex-col gap-6 max-md:px-0">
        {{ $slot }}
    </x-ui.card-content>
</x-ui.card>
