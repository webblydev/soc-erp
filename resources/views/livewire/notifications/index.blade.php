<div class="mx-auto flex w-full max-w-3xl flex-col gap-4">
    <div class="hidden items-center justify-between gap-4 md:flex">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Notifications') }}</h1>
        @if ($unreadCount > 0)
            <x-ui.button variant="outline" wire:click="markAllRead"><x-lucide-check-check /> {{ __('Mark all read') }}</x-ui.button>
        @endif
    </div>

    <div class="flex items-center gap-2">
        <x-ui.segmented-control name="notifications-filter" wire:model.live="filter" :value="$filter" class="h-11 flex-1 md:h-9 md:flex-none"
            :options="[['value' => 'all', 'label' => __('All')], ['value' => 'unread', 'label' => __('Unread (:count)', ['count' => $unreadCount])]]" />
        @if ($unreadCount > 0)
            <x-ui.button variant="ghost" size="icon" class="size-11 md:hidden" wire:click="markAllRead" :aria-label="__('Mark all read')"><x-lucide-check-check class="size-5" /></x-ui.button>
        @endif
    </div>

    <div wire:loading.delay wire:target="filter" class="flex flex-col gap-2">
        @foreach (range(1, 3) as $ignored)
            <x-ui.skeleton class="h-16 w-full" />
        @endforeach
    </div>

    <div wire:loading.remove wire:target="filter">
        @if ($notifications->isEmpty())
            <x-ui.empty class="border border-dashed py-12">
                <x-ui.empty-header>
                    <x-ui.empty-media variant="icon"><x-lucide-bell /></x-ui.empty-media>
                    <x-ui.empty-title>{{ $filter === 'unread' ? __('You are all caught up') : __('No notifications yet') }}</x-ui.empty-title>
                    <x-ui.empty-description>{{ __('Alerts about your account and work appear here.') }}</x-ui.empty-description>
                </x-ui.empty-header>
            </x-ui.empty>
        @else
            <x-ui.item-group class="gap-2">
                @foreach ($notifications as $notification)
                    <x-ui.item role="button" tabindex="0" variant="outline" wire:key="notification-{{ $notification->id }}" wire:click="open('{{ $notification->id }}')" wire:keydown.enter="open('{{ $notification->id }}')"
                        @class(['min-h-16 w-full cursor-pointer active:bg-accent', 'border-primary/40 bg-primary/5' => $notification->read_at === null])>
                        <x-ui.item-media variant="icon">
                            <x-dynamic-component :component="$notification->read_at === null ? 'lucide-bell-dot' : 'lucide-bell'" />
                        </x-ui.item-media>
                        <x-ui.item-content class="min-w-0">
                            <x-ui.item-title @class(['text-base md:text-sm', 'font-semibold' => $notification->read_at === null])>{{ $notification->data['title'] ?? __('Notification') }}</x-ui.item-title>
                            @if (filled($notification->data['body'] ?? null))
                                <x-ui.item-description class="text-sm">{{ $notification->data['body'] }}</x-ui.item-description>
                            @endif
                            <p class="text-sm text-muted-foreground">{{ $notification->created_at->diffForHumans() }}</p>
                        </x-ui.item-content>
                        @if (filled($notification->data['url'] ?? null))
                            <x-lucide-chevron-right class="size-5 shrink-0 text-muted-foreground" />
                        @endif
                    </x-ui.item>
                @endforeach
            </x-ui.item-group>

            @if ($hasMore)
                <div wire:intersect="loadMore" class="flex justify-center py-4">
                    <x-ui.button variant="ghost" class="h-11" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                        <x-lucide-loader-circle class="animate-spin" wire:loading wire:target="loadMore" />
                        {{ __('Load more') }}
                    </x-ui.button>
                </div>
            @endif
        @endif
    </div>
</div>
