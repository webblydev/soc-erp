<?php

namespace App\Modules\Estimation\Notifications;

use App\Models\User;
use App\Modules\Estimation\Models\Estimate;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Queue\SerializesModels;

/**
 * estimation.estimates.approved / estimation.estimates.rejected (docs/05 §9): the preparer hears
 * the decision.
 */
class EstimateDecided extends PreferenceNotification
{
    use SerializesModels;

    public function __construct(public Estimate $estimate, public bool $approved)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return $this->approved ? 'estimation.estimates.approved' : 'estimation.estimates.rejected';
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => $this->approved
                ? __('Estimate :number was approved', ['number' => $this->estimate->estimate_number])
                : __('Estimate :number was rejected', ['number' => $this->estimate->estimate_number]),
            'body' => $this->approved ? $this->estimate->title : (string) $this->estimate->rejection_note,
            'url' => route('estimation.estimates.show', $this->estimate),
        ];
    }
}
