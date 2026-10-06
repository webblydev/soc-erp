<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Notifications\Bell;
use App\Modules\Foundation\Livewire\Notifications\Index;
use App\Modules\Foundation\Notifications\NewIpSignIn;
use App\Modules\Foundation\Notifications\PasswordResetByAdmin;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => null]);
});

test('the app shell shows the bell with the unread count', function () {
    $this->user->notify(new PasswordResetByAdmin);
    $this->user->notify(new NewIpSignIn('10.0.0.9', '06-Oct-2026 09:00'));

    $this->actingAs($this->user)->get(route('dashboard'))->assertSeeLivewire(Bell::class);

    Livewire::actingAs($this->user)->test(Bell::class)->assertSet('unreadCount', 2)->assertSee('2');
});

test('the inbox lists only the user\'s own notifications, newest first', function () {
    $this->user->notify(new PasswordResetByAdmin);
    $this->travel(1)->minute();
    $this->user->notify(new NewIpSignIn('10.0.0.9', '06-Oct-2026 09:00'));
    User::factory()->create(['email' => null])->notify(new NewIpSignIn('10.9.9.9', '06-Oct-2026 09:00'));

    $this->actingAs($this->user)->get(route('notifications.index'))
        ->assertOk()
        ->assertSeeInOrder(['New sign-in from 10.0.0.9', 'Your password was reset'])
        ->assertDontSee('10.9.9.9');
});

test('the unread filter hides read notifications', function () {
    $this->user->notify(new PasswordResetByAdmin);
    $this->user->notify(new NewIpSignIn('10.0.0.9', '06-Oct-2026 09:00'));
    $this->user->notifications()->where('type', PasswordResetByAdmin::class)->first()->markAsRead();

    Livewire::actingAs($this->user)->test(Index::class)
        ->set('filter', 'unread')
        ->assertSee('New sign-in from 10.0.0.9')
        ->assertDontSee('Your password was reset');
});

test('opening a notification marks it read and follows its link', function () {
    $this->user->notify(new NewIpSignIn('10.0.0.9', '06-Oct-2026 09:00'));
    $notification = $this->user->notifications()->sole();

    Livewire::actingAs($this->user)->test(Index::class)
        ->call('open', $notification->id)
        ->assertRedirect(route('profile.edit'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('another user\'s notification cannot be opened', function () {
    $other = User::factory()->create(['email' => null]);
    $other->notify(new PasswordResetByAdmin);

    Livewire::actingAs($this->user)->test(Index::class)
        ->call('open', $other->notifications()->sole()->id)
        ->assertNotFound();
});

test('mark all read clears the unread count', function () {
    $this->user->notify(new PasswordResetByAdmin);
    $this->user->notify(new PasswordResetByAdmin);

    Livewire::actingAs($this->user)->test(Index::class)->call('markAllRead')->assertDispatched('notifications-read');

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

test('more notifications load as the list scrolls', function () {
    foreach (range(1, Index::PAGE_SIZE + 3) as $ignored) {
        $this->user->notify(new PasswordResetByAdmin);
    }

    Livewire::actingAs($this->user)->test(Index::class)
        ->assertViewHas('notifications', fn ($notifications) => $notifications->count() === Index::PAGE_SIZE)
        ->call('loadMore')
        ->assertViewHas('notifications', fn ($notifications) => $notifications->count() === Index::PAGE_SIZE + 3)
        ->assertViewHas('hasMore', false);
});
