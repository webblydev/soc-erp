<?php

namespace App\Modules\Foundation\Livewire\Profile;

use App\Http\Middleware\HandleImpersonation;
use App\Models\User;
use App\Modules\Foundation\Actions\ChangePassword;
use App\Modules\Foundation\Actions\SaveNotificationPreferences;
use App\Modules\Foundation\Actions\UpdateProfile;
use App\Modules\Foundation\Models\NotificationPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Profile')]
class Edit extends Component
{
    use WithFileUploads;

    public const TABS = ['details', 'password', 'notifications'];

    #[Url(except: 'details')]
    public string $tab = 'details';

    public string $name = '';

    public string $phone = '';

    /** @var UploadedFile|null */
    public $avatar = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Notification key (dots replaced by "__", so wire:model paths stay intact) → channel → enabled.
     *
     * @var array<string, mixed>
     */
    public array $notifications = [];

    public function mount(): void
    {
        $this->guardTab();

        $user = $this->user();
        $this->name = $user->name;
        $this->phone = (string) $user->phone;

        foreach (NotificationPreference::matrixFor($user) as $key => $channels) {
            $this->notifications[str_replace('.', '__', $key)] = $channels;
        }
    }

    public function updatedTab(): void
    {
        $this->guardTab();
    }

    public function saveDetails(UpdateProfile $updateProfile): void
    {
        $updateProfile->handle($this->user(), ['name' => $this->name, 'phone' => $this->phone], $this->avatar instanceof UploadedFile ? $this->avatar : null);

        $this->reset('avatar');
        $this->dispatch('toast', type: 'success', description: __('Profile saved.'));
    }

    public function savePassword(ChangePassword $changePassword): void
    {
        abort_if(HandleImpersonation::isActive(), 403);

        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::default()],
        ]);

        $changePassword->handle($this->user(), $this->password);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', type: 'success', description: __('Password updated.'));
    }

    public function saveNotifications(SaveNotificationPreferences $savePreferences): void
    {
        $matrix = [];

        foreach ($this->notifications as $key => $channels) {
            if (! is_array($channels)) {
                continue;
            }

            $matrix[str_replace('__', '.', $key)] = array_map(fn (mixed $enabled): bool => (bool) $enabled, $channels);
        }

        $savePreferences->handle($this->user(), $matrix);
        $this->dispatch('toast', type: 'success', description: __('Notification preferences saved.'));
    }

    public function render(): View
    {
        return view('livewire.profile.edit', [
            'user' => $this->user(),
            'availableTabs' => $this->availableTabs(),
            'notificationKeys' => config('notifications.keys', []),
        ]);
    }

    /**
     * The Password tab is hidden while impersonating, so the impersonator
     * cannot change the target's credentials.
     *
     * @return list<string>
     */
    private function availableTabs(): array
    {
        $impersonating = HandleImpersonation::isActive();

        return array_values(array_filter(
            self::TABS,
            fn (string $tab): bool => match ($tab) {
                'password' => ! $impersonating,
                default => true,
            },
        ));
    }

    private function guardTab(): void
    {
        if (! in_array($this->tab, $this->availableTabs(), true)) {
            $this->tab = 'details';
        }
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
