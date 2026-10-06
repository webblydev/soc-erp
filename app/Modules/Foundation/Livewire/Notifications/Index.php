<?php

namespace App\Modules\Foundation\Livewire\Notifications;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The in-app inbox: the signed-in user's database notifications (docs/01 §3.12).
 */
class Index extends Component
{
    public const PAGE_SIZE = 20;

    #[Url]
    public string $filter = 'all';

    public int $limit = self::PAGE_SIZE;

    public function updatedFilter(): void
    {
        $this->filter = $this->filter === 'unread' ? 'unread' : 'all';
        $this->limit = self::PAGE_SIZE;
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    public function open(string $id): void
    {
        $notification = $this->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $this->dispatch('notifications-read');

        $url = $notification->data['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->dispatch('notifications-read');
        $this->dispatch('toast', type: 'success', description: __('All notifications marked as read.'));
    }

    public function render(): View
    {
        $query = $this->filter === 'unread' ? $this->user()->unreadNotifications() : $this->user()->notifications();
        $notifications = $query->latest()->limit($this->limit + 1)->get();

        return view('livewire.notifications.index', [
            'notifications' => $notifications->take($this->limit),
            'hasMore' => $notifications->count() > $this->limit,
            'unreadCount' => $this->user()->unreadNotifications()->count(),
        ])->title(__('Notifications'));
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
