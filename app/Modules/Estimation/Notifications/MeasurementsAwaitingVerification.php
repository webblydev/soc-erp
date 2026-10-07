<?php

namespace App\Modules\Estimation\Notifications;

use App\Models\User;
use App\Support\Notifications\PreferenceNotification;

/**
 * site.mb.awaiting_verification (docs/05 §9): the daily count of MB entries a PM has to verify.
 */
class MeasurementsAwaitingVerification extends PreferenceNotification
{
    public function __construct(public int $count)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'site.mb.awaiting_verification';
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => trans_choice(':count measurement is waiting for verification|:count measurements are waiting for verification', $this->count),
            'body' => __('Recorded more than a day ago on your projects.'),
            'url' => route('site.mb.index', ['preset' => 'awaiting']),
        ];
    }
}
