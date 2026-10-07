<?php

namespace App\Modules\Estimation\Notifications;

use App\Models\User;
use App\Modules\Estimation\Models\Estimate;
use App\Support\Money;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Queue\SerializesModels;

/**
 * estimation.estimates.submitted (docs/05 §9): an estimate waits for the approver.
 */
class EstimateAwaitingApproval extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public Estimate $estimate)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'estimation.estimates.submitted';
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => __('Estimate :number is waiting for approval', ['number' => $this->estimate->estimate_number]),
            'body' => $this->estimate->title.' · '.Money::format($this->estimate->total_amount),
            'url' => route('estimation.estimates.show', $this->estimate),
        ];
    }
}
