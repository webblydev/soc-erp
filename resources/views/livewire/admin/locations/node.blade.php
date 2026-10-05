@php($isOpen = in_array($node->id, $expanded, true))
<li role="treeitem" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" wire:key="node-{{ $node->id }}">
    <div class="flex min-h-11 items-center gap-1 rounded-md pe-1 hover:bg-accent/50" style="padding-inline-start: {{ $depth * 1.25 }}rem">
        @if ($node->children_count > 0)
            <button type="button" wire:click="toggle({{ $node->id }})" class="flex size-11 items-center justify-center" aria-label="{{ $isOpen ? __('Collapse') : __('Expand') }}">
                <x-dynamic-component :component="$isOpen ? 'lucide-chevron-down' : 'lucide-chevron-right'" class="size-4" />
            </button>
        @else
            <span class="size-11"></span>
        @endif

        <span @class(['flex-1 truncate text-base md:text-sm', 'text-muted-foreground line-through' => ! $node->is_active])>{{ $node->name }}</span>

        @if ($node->location_level_id !== $areaLevelId)
            @can('admin.locations.create')
                <x-ui.button variant="ghost" size="icon" class="size-11 md:size-8" wire:click="addChild({{ $node->id }})" :aria-label="__('Add child')"><x-lucide-plus /></x-ui.button>
            @endcan
        @endif
        @can('admin.locations.update')
            <x-ui.button variant="ghost" size="icon" class="size-11 md:size-8" wire:click="edit({{ $node->id }})" :aria-label="__('Edit')"><x-lucide-pencil /></x-ui.button>
        @endcan
        @can('admin.locations.deactivate')
            <x-ui.button variant="ghost" size="icon" class="size-11 md:size-8" wire:click="toggleActive({{ $node->id }})" :aria-label="$node->is_active ? __('Deactivate') : __('Activate')">
                <x-dynamic-component :component="$node->is_active ? 'lucide-eye-off' : 'lucide-eye'" />
            </x-ui.button>
        @endcan
    </div>

    @if ($isOpen)
        <ul role="group" class="flex flex-col gap-1">
            @foreach ($childrenByParent->get($node->id, collect()) as $child)
                @include('livewire.admin.locations.node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
