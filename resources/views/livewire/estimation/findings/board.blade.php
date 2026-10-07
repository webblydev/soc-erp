@php
    $selectClass = 'h-11 text-base md:h-9 md:text-sm';
    $activeFilters = count(array_filter($filters, 'filled'));
@endphp

<div class="flex flex-col gap-4 pb-24 md:pb-0">
    <div class="flex flex-wrap items-end gap-3">
        <x-ui.field class="min-w-48 flex-1 md:flex-none">
            <x-ui.field-label for="board-project">{{ __('Project') }}</x-ui.field-label>
            <x-ui.select native id="board-project" wire:model.live="filters.project" :class="$selectClass">
                <option value="">{{ __('Any project') }}</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->project_number }}">{{ $project->project_number }} — {{ $project->name }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field class="min-w-36 flex-1 md:flex-none">
            <x-ui.field-label for="board-severity">{{ __('Severity') }}</x-ui.field-label>
            <x-ui.select native id="board-severity" wire:model.live="filters.severity" :class="$selectClass">
                <option value="">{{ __('Any') }}</option>
                @foreach ($severities as $severity)
                    <option value="{{ $severity->id }}">{{ $severity->name }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field class="min-w-48 flex-1 md:flex-none">
            <x-ui.field-label for="board-responsible">{{ __('Responsible') }}</x-ui.field-label>
            <x-ui.select native id="board-responsible" wire:model.live="filters.responsible" :class="$selectClass">
                <option value="">{{ __('Anyone') }}</option>
                @foreach ($responsibles as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <label class="flex min-h-11 items-center gap-2 text-sm">
            <x-ui.checkbox native wire:model.live="filters.overdue" value="1" /> {{ __('Overdue only') }}
        </label>
        @if ($activeFilters > 0)
            <x-ui.button variant="ghost" size="sm" class="h-11 md:h-9" wire:click="clearFilters">{{ __('Clear filters') }}</x-ui.button>
        @endif
    </div>

    {{-- Desktop board --}}
    <div class="hidden gap-4 overflow-x-auto md:grid md:grid-cols-4">
        @foreach ($columns as $column)
            <section class="flex min-w-60 flex-col gap-2 rounded-md bg-muted/40 p-2" wire:key="column-{{ $column['status']->id }}">
                <header class="flex items-center justify-between px-1 text-sm font-medium">
                    <span>{{ $column['status']->name }}</span>
                    <span class="tabular-nums text-muted-foreground">{{ $column['count'] }}</span>
                </header>
                <div class="flex min-h-24 flex-col gap-2" wire:sort="moveFinding" wire:sort:group="findings" wire:sort:group-id="{{ $column['status']->id }}">
                    @foreach ($column['findings'] as $finding)
                        <x-ui.card class="cursor-grab gap-1 p-3 active:cursor-grabbing" wire:key="board-finding-{{ $finding->id }}" wire:sort:item="{{ $finding->id }}">
                            <a data-detail-modal href="{{ route('site.inspections.show', $finding->inspection->inspection_number) }}" wire:navigate class="font-medium hover:underline">{{ $finding->location ?: \Illuminate\Support\Str::limit($finding->description, 40) }}</a>
                            <span class="line-clamp-2 text-sm">{{ $finding->description }}</span>
                            <span class="text-sm text-muted-foreground"><span class="font-mono">{{ $finding->project->project_number }}</span> · {{ $finding->responsibleName() ?? __('Nobody') }}</span>
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <x-ui.badge :tone="$finding->severity->color ?? 'neutral'">{{ $finding->severity->name }}</x-ui.badge>
                                <span @class(['tabular-nums', 'text-destructive' => $finding->isOverdue()])>{{ $finding->due_date?->format('d M') ?? '—' }}</span>
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    {{-- Mobile: status chips and a list --}}
    <div class="flex flex-col gap-3 md:hidden">
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4">
            @foreach ($columns as $column)
                <x-ui.button :variant="$mobileStatus === $column['status']->code ? 'default' : 'outline'" size="sm" class="h-11 shrink-0" wire:click="$set('mobileStatus', '{{ $column['status']->code }}')">
                    {{ $column['status']->name }} <span class="tabular-nums">({{ $column['count'] }})</span>
                </x-ui.button>
            @endforeach
        </div>
        <x-ui.item-group class="gap-2">
            @forelse ($columns->firstWhere('status.code', $mobileStatus)['findings'] ?? [] as $finding)
                <x-ui.item variant="outline" class="min-h-16 py-2 active:bg-accent" wire:key="m-finding-{{ $finding->id }}" :href="route('site.inspections.show', $finding->inspection->inspection_number)" wire:navigate>
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-base"><span class="truncate">{{ $finding->location ?: $finding->description }}</span></x-ui.item-title>
                        <x-ui.item-description class="truncate text-sm"><span class="font-mono">{{ $finding->project->project_number }}</span> · {{ $finding->responsibleName() ?? __('Nobody') }}</x-ui.item-description>
                        <span @class(['text-sm tabular-nums', 'text-destructive' => $finding->isOverdue(), 'text-muted-foreground' => ! $finding->isOverdue()])>{{ $finding->due_date ? __('Due :date', ['date' => $finding->due_date->format('d-M-Y')]) : __('No due date') }}</span>
                    </x-ui.item-content>
                    <x-ui.badge :tone="$finding->severity->color ?? 'neutral'" class="shrink-0 text-sm">{{ $finding->severity->name }}</x-ui.badge>
                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                </x-ui.item>
            @empty
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('No findings here.') }}</p>
            @endforelse
        </x-ui.item-group>
    </div>

    <x-shell.sheet id="finding-close" :title="__('Close finding')" :description="__('Say what was done; add an after photo if you have one.')">
        <x-ui.field>
            <x-ui.field-label for="closing-note">{{ __('Closure note') }} *</x-ui.field-label>
            <x-ui.textarea id="closing-note" wire:model="closingNote" rows="3" class="text-base md:text-sm" />
            <x-ui.field-error :messages="$errors->get('closingNote')" />
        </x-ui.field>
        <x-ui.field>
            <x-ui.field-label for="closing-photo">{{ __('After photo') }}</x-ui.field-label>
            <input type="file" id="closing-photo" wire:model="closingPhoto" accept="image/*" capture="environment" class="min-h-11 text-base md:text-sm" />
            <x-ui.field-error :messages="$errors->get('closingPhoto')" />
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-finding-close')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button wire:click="close" wire:loading.attr="disabled">{{ __('Close finding') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
