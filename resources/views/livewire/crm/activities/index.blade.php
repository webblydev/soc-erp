@php
    $tabs = ['overdue' => __('Overdue'), 'today' => __('Today'), 'upcoming' => __('Upcoming'), 'done' => __('Done')];
    $tabOptions = collect($tabs)->map(fn ($label, $key) => ['value' => $key, 'label' => $label.(($counts[$key] ?? null) ? ' ('.$counts[$key].')' : '')])->values()->all();
@endphp

<div class="flex flex-col gap-4 pb-24 md:pb-0">
    <div class="flex flex-wrap items-center gap-2">
        @if ($view === 'list')
            <x-ui.segmented-control name="activity-tab" wire:model.live="tab" :value="$tab" :options="$tabOptions" class="h-11 w-full md:h-9 md:w-auto" />
        @else
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="outline" size="icon" class="size-11 md:size-9" wire:click="previousWeek" :aria-label="__('Previous week')"><x-lucide-chevron-left /></x-ui.button>
                <x-ui.button variant="outline" class="h-11 md:h-9" wire:click="thisWeek">{{ __('This week') }}</x-ui.button>
                <x-ui.button variant="outline" size="icon" class="size-11 md:size-9" wire:click="nextWeek" :aria-label="__('Next week')"><x-lucide-chevron-right /></x-ui.button>
                <span class="text-sm font-medium">{{ $weekStart->format('d M') }} – {{ $weekStart->copy()->addDays(6)->format('d M Y') }}</span>
            </div>
        @endif

        <div class="ms-auto flex items-center gap-2">
            @if ($owners->isNotEmpty())
                <x-ui.select native wire:model.live="owner" class="hidden h-9 w-48 md:flex" :aria-label="__('Owner')">
                    <option value="me">{{ __('Me') }}</option>
                    <option value="all">{{ __('Everyone I can see') }}</option>
                    @foreach ($owners as $person)
                        @continue($person->id === auth()->id())
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.button variant="outline" size="icon" class="size-11 md:hidden" x-on:click="$dispatch('open-sheet-activity-owner')" :aria-label="__('Owner')"><x-lucide-user-round /></x-ui.button>
            @endif
            <x-ui.segmented-control name="activity-view" wire:model.live="view" :value="$view" class="h-11 md:h-9"
                :options="[['value' => 'list', 'label' => __('List'), 'icon' => 'list'], ['value' => 'calendar', 'label' => __('Calendar'), 'icon' => 'calendar']]" />
        </div>
    </div>

    @if ($view === 'list')
        @include('livewire.crm.activities.partials.timeline', ['activities' => $activities, 'showSubject' => true])
        @if ($tab === 'done')
            <div>{{ $activities->links() }}</div>
        @endif
    @else
        <div class="hidden md:block">
            <x-ui.scheduler :events="$events" :days="$days" :start-hour="8" :end-hour="20" />
        </div>
        <div class="flex flex-col gap-4 md:hidden">
            @forelse (collect($events)->groupBy('day') as $day => $dayEvents)
                <section wire:key="agenda-{{ $day }}">
                    <h2 class="sticky top-[calc(3.5rem+env(safe-area-inset-top))] z-10 bg-background py-2 text-sm font-semibold">{{ $days[$day] }}</h2>
                    <x-ui.item-group class="gap-2">
                        @foreach ($dayEvents as $event)
                            <x-ui.item variant="outline" class="min-h-11">
                                <x-ui.item-content>
                                    <x-ui.item-title class="text-base">{{ $event['title'] }}</x-ui.item-title>
                                    <x-ui.item-description class="text-sm"><span class="tabular-nums">{{ $event['start'] }}</span>@if ($event['subject']) · {{ $event['subject'] }}@endif</x-ui.item-description>
                                </x-ui.item-content>
                                @unless ($event['open'])
                                    <x-ui.badge tone="success" class="text-sm">{{ __('Done') }}</x-ui.badge>
                                @endunless
                            </x-ui.item>
                        @endforeach
                    </x-ui.item-group>
                </section>
            @empty
                <p class="py-8 text-center text-sm text-muted-foreground">{{ __('Nothing this week.') }}</p>
            @endforelse
        </div>
    @endif

    @if ($owners->isNotEmpty())
        <x-shell.sheet id="activity-owner" :title="__('Owner')" :description="__('Show the activities of the chosen person.')">
            <x-ui.select native wire:model.live="owner" class="h-11 text-base" :aria-label="__('Owner')">
                <option value="me">{{ __('Me') }}</option>
                <option value="all">{{ __('Everyone I can see') }}</option>
                @foreach ($owners as $person)
                    @continue($person->id === auth()->id())
                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                @endforeach
            </x-ui.select>
            <x-slot:footer>
                <x-ui.button x-on:click="$dispatch('close-sheet-activity-owner')">{{ __('Done') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endif

    <x-shell.sheet id="activity-delete" :title="__('Delete activity?')" :description="__('The activity is removed from the timeline.')">
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-activity-delete')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button variant="destructive" wire:click="deleteActivity">{{ __('Delete') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
