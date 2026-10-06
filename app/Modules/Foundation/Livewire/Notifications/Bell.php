<?php

namespace App\Modules\Foundation\Livewire\Notifications;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Top-bar bell with the unread in-app notification count (docs/00 §7.1).
 */
class Bell extends Component
{
    public int $unreadCount = 0;

    public string $size = 'desktop';

    public function mount(string $size = 'desktop'): void
    {
        $this->size = $size;
        $this->refreshCount();
    }

    #[On('notifications-read')]
    public function refreshCount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->unreadCount = $user->unreadNotifications()->count();
    }

    public function render(): View
    {
        return view('livewire.notifications.bell');
    }
}
