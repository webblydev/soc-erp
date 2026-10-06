{{--
    The Action cell (first column after selection) of a desktop list table: a ⋮ button that opens
    the row's actions. Fill it with <x-shell.row-menu-item>.
--}}
<x-ui.table-cell class="w-14 text-center" data-test="row-actions">
    <x-ui.dropdown-menu>
        <x-ui.dropdown-menu-trigger>
            <x-ui.button variant="ghost" size="icon" class="size-7" :aria-label="__('Row actions')">
                <x-lucide-ellipsis-vertical />
            </x-ui.button>
        </x-ui.dropdown-menu-trigger>
        <x-ui.dropdown-menu-content align="start" class="min-w-44">
            {{ $slot }}
        </x-ui.dropdown-menu-content>
    </x-ui.dropdown-menu>
</x-ui.table-cell>
