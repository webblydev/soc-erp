<?php

namespace App\Modules\Foundation\Livewire\Profile;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Set up two-factor authentication')]
#[Layout('layouts::auth')]
class TwoFactorSetup extends Component
{
    public function render(): View
    {
        return view('livewire.profile.two-factor-setup');
    }
}
