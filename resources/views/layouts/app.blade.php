<x-layouts::app.sidebar :title="$title ?? null" :back="$back ?? null">
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset
    @isset($breadcrumbs)
        <x-slot:breadcrumbs>{{ $breadcrumbs }}</x-slot:breadcrumbs>
    @endisset
    @isset($quickCreate)
        <x-slot:quick-create>{{ $quickCreate }}</x-slot:quick-create>
    @endisset

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        {{ $slot }}
    </div>
</x-layouts::app.sidebar>
