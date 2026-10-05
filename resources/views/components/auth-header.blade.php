@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1 text-center">
    <h1 class="text-xl font-semibold tracking-tight">{{ $title }}</h1>
    <x-ui.typography variant="muted">{{ $description }}</x-ui.typography>
</div>
