{{-- One level of the org chart: each employee row, then their reports indented below. --}}
@foreach ($nodes as $node)
    @php($employee = $node['employee'])
    <x-ui.collapsible :open="$depth < 2" class="flex flex-col gap-2" wire:key="org-{{ $employee->id }}">
        <div class="flex items-center gap-2">
            @if ($node['children'] !== [])
                <x-ui.collapsible-trigger>
                    <x-ui.button variant="ghost" size="icon" class="size-11 md:size-9" :aria-label="__('Show or hide reports of :name', ['name' => $employee->full_name])">
                        <x-lucide-chevron-down class="size-4" />
                    </x-ui.button>
                </x-ui.collapsible-trigger>
            @else
                <span class="size-11 shrink-0 md:size-9"></span>
            @endif
            <x-ui.item variant="outline" class="min-h-11 flex-1 py-2 active:bg-accent" :href="route('hrm.employees.show', $employee)" wire:navigate>
                <x-employee-avatar :employee="$employee" class="size-8" />
                <x-ui.item-content class="min-w-0">
                    <x-ui.item-title class="text-base md:text-sm"><span class="truncate">{{ $employee->full_name }}</span></x-ui.item-title>
                    <x-ui.item-description class="truncate text-sm">{{ $employee->designation->name }}</x-ui.item-description>
                </x-ui.item-content>
                @if ($node['children'] !== [])
                    <x-ui.badge variant="secondary" class="shrink-0 text-sm" :aria-label="trans_choice(':count report|:count reports', count($node['children']))">{{ count($node['children']) }}</x-ui.badge>
                @endif
            </x-ui.item>
        </div>
        @if ($node['children'] !== [])
            <x-ui.collapsible-content>
                <div class="ms-5 flex flex-col gap-2 border-s ps-3 md:ms-4">
                    @include('livewire.hrm.partials.org-node', ['nodes' => $node['children'], 'depth' => $depth + 1])
                </div>
            </x-ui.collapsible-content>
        @endif
    </x-ui.collapsible>
@endforeach
