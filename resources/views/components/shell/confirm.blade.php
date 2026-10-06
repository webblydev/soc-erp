{{--
    A confirmation (delete and other irreversible actions): a centered dialog from md, a bottom
    sheet below md. It opens and closes on the same `open-sheet-{id}` / `close-sheet-{id}`
    events as x-shell.sheet, so a server action can confirm with $this->dispatch('open-sheet-…').
    The footer holds the Cancel and confirm buttons.
--}}
@props(['id', 'title', 'description'])

<x-ui.alert-dialog x-on:open-sheet-{{ $id }}.window="open = true" x-on:close-sheet-{{ $id }}.window="open = false" {{ $attributes }}>
    <x-shell.confirm-content>
        <x-ui.alert-dialog-header>
            <x-ui.alert-dialog-title>{{ $title }}</x-ui.alert-dialog-title>
            <x-ui.alert-dialog-description>{{ $description }}</x-ui.alert-dialog-description>
        </x-ui.alert-dialog-header>

        @if ($slot->isNotEmpty())
            <div class="flex flex-col gap-4">{{ $slot }}</div>
        @endif

        @isset($footer)
            <x-ui.alert-dialog-footer class="max-md:flex-row max-md:gap-2 [&>*]:h-11 [&>*]:flex-1 md:[&>*]:h-9 md:[&>*]:flex-none">
                {{ $footer }}
            </x-ui.alert-dialog-footer>
        @endisset
    </x-shell.confirm-content>
</x-ui.alert-dialog>
