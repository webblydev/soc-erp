<div class="flex flex-col gap-4">
    <div class="flex items-center gap-2">
        <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Search locations')" class="h-11 flex-1 text-base md:h-9 md:max-w-xs md:text-sm" />
        @can('admin.locations.create')
            <x-ui.button class="hidden md:inline-flex" wire:click="addChild"><x-lucide-plus /> {{ __('Add division') }}</x-ui.button>
        @endcan
    </div>

    @if ($results !== null)
        <x-ui.item-group class="gap-2">
            @forelse ($results as $location)
                <x-ui.item variant="outline" class="min-h-14" wire:key="result-{{ $location->id }}">
                    <x-ui.item-content>
                        <x-ui.item-title class="text-base md:text-sm">{{ $location->name }}</x-ui.item-title>
                        <x-ui.item-description class="text-sm">{{ $location->full_path }}</x-ui.item-description>
                    </x-ui.item-content>
                    @can('admin.locations.update')
                        <x-ui.button variant="ghost" size="icon" class="size-11" wire:click="edit({{ $location->id }})" :aria-label="__('Edit')"><x-lucide-pencil class="size-5" /></x-ui.button>
                    @endcan
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No locations match.') }}</p>
            @endforelse
        </x-ui.item-group>
    @else
        <ul class="flex flex-col gap-1" role="tree">
            @foreach ($roots as $node)
                @include('livewire.admin.locations.node', ['node' => $node, 'depth' => 0])
            @endforeach
        </ul>
    @endif

    @can('admin.locations.create')
        <button type="button" wire:click="addChild" aria-label="{{ __('Add division') }}"
            class="fixed end-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg active:scale-95 md:hidden">
            <x-lucide-plus class="size-6" />
        </button>
    @endcan

    <x-shell.sheet id="location" :title="$editingId ? __('Edit location') : __('Add location')" :description="$parentPath ? __('Under :path', ['path' => $parentPath]) : null">
        <form wire:submit="save" id="location-form" class="flex flex-col gap-4 pb-2">
            <x-ui.field>
                <x-ui.field-label for="location-name">{{ __('Name') }} *</x-ui.field-label>
                <x-ui.input id="location-name" wire:model="name" class="h-11 text-base md:h-9 md:text-sm" />
                <x-ui.field-error :messages="$errors->get('name')" />
                <x-ui.field-error :messages="$errors->get('parent_id')" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="location-name-bn">{{ __('Name (Bangla)') }}</x-ui.field-label>
                <x-ui.input id="location-name-bn" wire:model="name_bn" lang="bn" class="h-11 text-base md:h-9 md:text-sm" />
                <x-ui.field-error :messages="$errors->get('name_bn')" />
            </x-ui.field>
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="location-form">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
