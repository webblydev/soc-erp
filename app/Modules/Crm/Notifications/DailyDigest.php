<?php

namespace App\Modules\Crm\Notifications;

use App\Models\User;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Lead;
use App\Support\Notifications\PreferenceNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;

/**
 * crm.daily_digest (docs/03 §9): today's and overdue follow-ups, plus team overdue counts for managers.
 */
class DailyDigest extends PreferenceNotification
{
    use SerializesModels;

    public const ITEMS_PER_LIST = 10;

    /**
     * @param  Collection<int, CrmActivity>  $today
     * @param  Collection<int, CrmActivity>  $overdue
     * @param  array<string, int>  $teamOverdue  member name => overdue count
     */
    public function __construct(public Collection $today, public Collection $overdue, public array $teamOverdue)
    {
        parent::__construct();
    }

    public function key(): string
    {
        return 'crm.daily_digest';
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Your follow-ups for :date', ['date' => today()->format('d-M-Y')]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':today due today, :overdue overdue.', ['today' => $this->today->count(), 'overdue' => $this->overdue->count()]));

        foreach ([__('Overdue') => $this->overdue, __('Today') => $this->today] as $heading => $items) {
            if ($items->isNotEmpty()) {
                $mail->line("**{$heading}**");

                foreach ($items->take(self::ITEMS_PER_LIST) as $activity) {
                    $mail->line($this->itemLine($activity));
                }
            }
        }

        if ($this->teamOverdue !== []) {
            $mail->line('**'.__('Team overdue').'**');

            foreach ($this->teamOverdue as $name => $count) {
                $mail->line("{$name}: {$count}");
            }
        }

        return $mail->action(__('Open my activities'), route('crm.activities.index'));
    }

    /**
     * "09:30 Call — Rahim Uddin (L-000124)".
     */
    private function itemLine(CrmActivity $activity): string
    {
        $subject = $activity->subject;
        $number = $subject instanceof Lead ? $subject->lead_number : $subject?->getAttribute('customer_number');

        return trim(sprintf('%s %s — %s (%s)', $activity->scheduled_at?->format('H:i'), $activity->type->name, $subject?->getAttribute('name'), $number));
    }
}
