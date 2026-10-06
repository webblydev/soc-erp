<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <h1 class="hidden text-2xl font-semibold tracking-tight md:block">{{ __('Org chart') }}</h1>
        <x-ui.field class="md:w-72">
            <x-ui.field-label for="org-department">{{ __('Department') }}</x-ui.field-label>
            <x-lookup-select table="departments" :placeholder="__('All departments')" id="org-department" wire:model.live="department" />
        </x-ui.field>
    </div>

    @if ($tree === [])
        <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No employees found.') }}</p>
    @else
        <div class="flex flex-col gap-2">
            @include('livewire.hrm.partials.org-node', ['nodes' => $tree, 'depth' => 0])
        </div>
    @endif
</div>
