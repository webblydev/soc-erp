<x-layouts::app.sidebar :title="$title ?? null">
    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        {{ $slot }}
    </div>
</x-layouts::app.sidebar>
