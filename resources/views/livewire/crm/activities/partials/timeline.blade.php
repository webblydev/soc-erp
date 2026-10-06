{{--
    Activity list for lead, customer and My activities screens (docs/03 §5.3, §5.5).
    $activities: open first (by scheduled_at), then done (latest first), with type, outcome and owner loaded.
    $showSubject: link each row to its lead or customer. Delete calls confirmDeleteActivity() on the host.
--}}
@php($actor = auth()->user())

<x-ui.item-group class="gap-2">
    @forelse ($activities as $activity)
        <x-ui.item variant="outline" class="items-start" wire:key="activity-{{ $activity->id }}">
            <x-ui.item-media variant="icon" class="size-11 md:size-9">
                <x-dynamic-component :component="'lucide-'.($activity->type->icon ?: 'circle')" />
            </x-ui.item-media>
            <x-ui.item-content class="min-w-0">
                <x-ui.item-title class="flex flex-wrap items-center gap-2 text-base md:text-sm">
                    <span>{{ $activity->title }}</span>
                    @if ($activity->isOverdue())
                        <x-ui.badge tone="danger" class="text-sm">{{ __('Overdue') }}</x-ui.badge>
                    @elseif (! $activity->isOpen())
                        <x-ui.badge tone="success" class="text-sm">{{ __('Done') }}</x-ui.badge>
                    @endif
                </x-ui.item-title>
                @if ($activity->description)
                    <x-ui.item-description class="line-clamp-2 text-sm">{{ $activity->description }}</x-ui.item-description>
                @endif
                <x-ui.item-description class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    <span class="tabular-nums">{{ ($activity->completed_at ?? $activity->scheduled_at)?->format('d-M-Y h:i A') }}</span>
                    <span>{{ $activity->owner->name }}</span>
                    @if ($activity->outcome)
                        <x-ui.badge tone="neutral" class="text-sm">{{ $activity->outcome->name }}</x-ui.badge>
                    @endif
                    @if ($showSubject && $activity->subject)
                        @php($subjectUrl = $activity->subject instanceof \App\Modules\Crm\Models\Lead
                            ? route('crm.leads.show', $activity->subject)
                            : (Route::has('crm.customers.show') ? route('crm.customers.show', $activity->subject) : null))
                        @if ($subjectUrl)
                            <a data-detail-modal href="{{ $subjectUrl }}" wire:navigate class="font-medium text-foreground hover:underline">{{ $activity->subject->name }}</a>
                        @else
                            <span class="font-medium text-foreground">{{ $activity->subject->name }}</span>
                        @endif
                    @endif
                </x-ui.item-description>
            </x-ui.item-content>
            @if ($activity->isOpen() && $actor->can('crm.activities.update'))
                <x-ui.item-actions class="flex shrink-0 gap-2 max-md:flex-col">
                    <x-ui.button variant="outline" size="sm" class="h-11 md:h-8"
                        x-on:click="$dispatch('crm-log-activity', { subjectType: @js($activity->subject_type), subjectId: {{ $activity->subject_id }}, mode: 'complete', activity: {{ $activity->id }} })">
                        <x-lucide-check /> <span class="max-md:sr-only">{{ __('Mark done') }}</span>
                    </x-ui.button>
                    <x-ui.button variant="ghost" size="sm" class="h-11 md:h-8"
                        x-on:click="$dispatch('crm-log-activity', { subjectType: @js($activity->subject_type), subjectId: {{ $activity->subject_id }}, mode: 'reschedule', activity: {{ $activity->id }} })">
                        <x-lucide-calendar-clock /> <span class="max-md:sr-only">{{ __('Reschedule') }}</span>
                    </x-ui.button>
                </x-ui.item-actions>
            @endif
            @can('crm.activities.delete')
                <x-ui.button variant="ghost" size="icon" class="size-11 shrink-0 md:size-8" wire:click="confirmDeleteActivity({{ $activity->id }})" :aria-label="__('Delete activity')">
                    <x-lucide-trash-2 />
                </x-ui.button>
            @endcan
        </x-ui.item>
    @empty
        <p class="py-8 text-center text-sm text-muted-foreground">{{ __('No activities yet.') }}</p>
    @endforelse
</x-ui.item-group>
