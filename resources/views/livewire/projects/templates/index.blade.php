<div>
    <x-shell.list
        :search-placeholder="__('Search templates')"
        :create-url="route('projects.templates.create')"
        :create-label="__('New template')"
        :exportable="true"
        :has-more="$this->hasMoreRows"
    >
        <x-slot:desktop>
            <x-shell.bulk-bar :exportable="true" :deletable="true" />

            <x-ui.table variant="bordered">
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-shell.select-all :ids="$rows->pluck('id')" />
                        <x-ui.table-head class="w-14 text-center">{{ __('Action') }}</x-ui.table-head>
                        <x-ui.table-head><x-shell.sort-header key="name" :label="__('Name')" :$sort :$direction /></x-ui.table-head>
                        <x-ui.table-head>{{ __('Service') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Project type') }}</x-ui.table-head>
                        <x-ui.table-head class="text-end">{{ __('Tasks') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($rows as $template)
                        <x-ui.table-row wire:key="template-{{ $template->id }}">
                            <x-shell.select-row :id="$template->id" :label="$template->name" />
                            <x-shell.row-menu>
                                <x-shell.row-menu-item icon="pencil" data-detail-modal :href="route('projects.templates.edit', $template)">{{ __('Edit') }}</x-shell.row-menu-item>
                                <x-shell.row-menu-item icon="trash-2" destructive wire:click="deleteRecord({{ $template->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $template->name]) }}">{{ __('Delete') }}</x-shell.row-menu-item>
                            </x-shell.row-menu>
                            <x-ui.table-cell class="font-medium">
                                <a data-detail-modal href="{{ route('projects.templates.edit', $template) }}" wire:navigate class="hover:underline">{{ $template->name }}</a>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $template->service?->name ?? '—' }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $template->projectType?->name ?? '—' }}</x-ui.table-cell>
                            <x-ui.table-cell class="text-end tabular-nums">{{ $template->items_count }}</x-ui.table-cell>
                            <x-ui.table-cell><x-ui.badge :tone="$template->is_active ? 'success' : 'neutral'">{{ $template->is_active ? __('Active') : __('Inactive') }}</x-ui.badge></x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="7" class="py-10 text-center text-muted-foreground">{{ __('No templates yet.') }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>

            <div class="mt-4">{{ $rows->links() }}</div>
        </x-slot:desktop>

        <x-slot:mobile>
            @forelse ($mobileRows as $template)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-template-{{ $template->id }}" :href="route('projects.templates.edit', $template)" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-base"><span class="truncate">{{ $template->name }}</span></x-ui.item-title>
                        <x-ui.item-description class="text-sm">{{ trans_choice(':count task|:count tasks', $template->items_count) }}@if ($template->service) · {{ $template->service->name }}@endif</x-ui.item-description>
                    </x-ui.item-content>
                    <x-ui.badge :tone="$template->is_active ? 'success' : 'neutral'" class="shrink-0 text-sm">{{ $template->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No templates yet.') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-shell.list>
</div>
