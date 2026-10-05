<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <h3 class="font-semibold">{{ __('Delete account') }}</h3>
        <x-ui.typography variant="muted">{{ __('Delete your account and all of its resources') }}</x-ui.typography>
    </div>

    <x-ui.dialog-trigger for="confirm-user-deletion">
        <x-ui.button variant="destructive" data-test="delete-user-button">
            {{ __('Delete account') }}
        </x-ui.button>
    </x-ui.dialog-trigger>

    <livewire:pages::settings.delete-user-modal />
</section>
