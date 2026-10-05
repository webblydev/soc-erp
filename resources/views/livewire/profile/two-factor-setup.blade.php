<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Set up two-factor authentication')" :description="__('Your role requires a code from an authenticator app at sign-in. Set it up to continue.')" />

    <livewire:foundation.profile.two-factor :forced="true" />

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <x-ui.button type="submit" variant="link">{{ __('Log out') }}</x-ui.button>
    </form>
</div>
