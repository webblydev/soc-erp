<x-layouts::app :title="__('Dashboard')">
    <div class="grid grid-cols-1 auto-rows-min gap-4 md:grid-cols-3">
        <div class="relative aspect-video overflow-hidden rounded-xl border">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-foreground/10" />
        </div>
        <div class="relative aspect-video overflow-hidden rounded-xl border">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-foreground/10" />
        </div>
        <div class="relative aspect-video overflow-hidden rounded-xl border">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-foreground/10" />
        </div>
    </div>
    <div class="relative min-h-[60vh] flex-1 overflow-hidden rounded-xl border">
        <x-placeholder-pattern class="absolute inset-0 size-full stroke-foreground/10" />
    </div>
</x-layouts::app>
