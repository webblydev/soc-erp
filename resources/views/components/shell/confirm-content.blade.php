{{-- The panel of a confirmation: x-ui.alert-dialog-content, turned into a bottom sheet below md. --}}
<x-ui.alert-dialog-content {{ $attributes->twMerge('max-md:inset-x-0 max-md:top-auto max-md:bottom-0 max-md:max-w-none max-md:translate-x-0 max-md:translate-y-0 max-md:rounded-b-none max-md:pb-[calc(1.5rem+env(safe-area-inset-bottom))]') }}>
    {{ $slot }}
</x-ui.alert-dialog-content>
