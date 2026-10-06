<?php

namespace App\Modules\Foundation\Concerns;

use Livewire\Attributes\Locked;

/**
 * For create/edit forms that also open in the detail modal: DetailModal passes the page under
 * the modal as `returnUrl`, so saving closes the modal and reloads that page instead of
 * leaving it. As a full page the form redirects to its usual route.
 */
trait SavesFromDetailModal
{
    #[Locked]
    public ?string $returnUrl = null;

    /**
     * @param  array<string, mixed>|mixed  $parameters
     */
    protected function redirectAfterSave(string $message, string $route, mixed $parameters = []): void
    {
        session()->flash('success', $message);

        if ($this->returnUrl !== null) {
            $this->redirect($this->returnUrl, navigate: true);

            return;
        }

        $this->redirectRoute($route, $parameters, navigate: true);
    }
}
