{{--
    The Action cell (first column after selection) of a desktop list table: the row's actions as
    inline icon buttons with tooltips. Fill it with <x-shell.row-menu-item>.
--}}
<x-ui.table-cell class="w-14 text-center" data-test="row-actions">
    <div class="flex items-center justify-center gap-0.5 whitespace-nowrap">
        {{ $slot }}
    </div>
</x-ui.table-cell>
