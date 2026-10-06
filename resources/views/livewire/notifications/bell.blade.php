<div wire:poll.60s="refreshCount" class="relative">
    <x-ui.button variant="ghost" size="icon" :class="$size === 'mobile' ? 'size-11' : ''" :href="route('notifications.index')" wire:navigate
        :aria-label="$unreadCount > 0 ? trans_choice(':count unread notification|:count unread notifications', $unreadCount, ['count' => $unreadCount]) : __('Notifications')">
        <x-lucide-bell @class(['size-5' => $size === 'mobile']) />
    </x-ui.button>
    @if ($unreadCount > 0)
        <span aria-hidden="true" class="pointer-events-none absolute end-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold leading-none text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
    @endif
</div>
