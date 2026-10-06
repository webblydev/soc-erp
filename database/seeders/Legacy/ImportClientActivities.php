<?php

namespace Database\Seeders\Legacy;

use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Services\LeadFollowUps;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * v1 follow-up history → CRM activities (legacy seed spec L9): each tbl_clientdetails note and
 * the client comment become completed notes, a reminder still ahead becomes an open follow-up.
 * Notes of sold clients go on the customer. A lead or customer that already has activities is
 * skipped, so re-running adds nothing.
 */
class ImportClientActivities
{
    public function __construct(private LeadFollowUps $followUps) {}

    public function run(LegacyContext $context): int
    {
        $created = 0;
        $noteType = (int) $context->idFor(ActivityType::class, 'NOTE');
        $followUpType = (int) $context->idFor(ActivityType::class, 'FOLLOW_UP');

        /** @var Collection<int, Collection<int, object>> $details */
        $details = $context->legacy()->table('tbl_clientdetails')->orderBy('id')->get()->groupBy('client_id');
        $clients = $context->legacy()->table('tbl_client')->whereIn('id', array_keys($context->leads))->get(['id', 'comment', 'reminder', 'add_time'])->keyBy('id');

        foreach ($context->leads as $clientId => $target) {
            [$subjectType, $subjectId] = $target['customer'] !== null
                ? [(new Customer)->getMorphClass(), $target['customer']]
                : [(new Lead)->getMorphClass(), $target['lead']];

            if (CrmActivity::withTrashed()->where('subject_type', $subjectType)->where('subject_id', $subjectId)->exists()) {
                continue;
            }

            $client = $clients[$clientId] ?? null;
            $rows = [];

            foreach ($details[$clientId] ?? [] as $detail) {
                $note = trim((string) $detail->note);

                if ($note === '') {
                    continue;
                }

                $rows[] = [
                    'activity_type_id' => $noteType,
                    'title' => Str::limit((string) preg_replace('/\s+/', ' ', $note), 60),
                    'description' => $note,
                    'completed_at' => LegacyMap::dateTime($detail->added_date) ?? LegacyMap::dateTime($detail->date ?? null) ?? now(),
                    'owner_user_id' => $context->usernames[Str::lower(trim((string) $detail->added_by))] ?? $target['owner'],
                ];
            }

            if ($client !== null && trim((string) $client->comment) !== '') {
                $rows[] = [
                    'activity_type_id' => $noteType,
                    'title' => 'Legacy comments',
                    'description' => trim((string) $client->comment),
                    'completed_at' => LegacyMap::dateTime($client->add_time) ?? now(),
                    'owner_user_id' => $target['owner'],
                ];
            }

            $reminder = $client !== null ? LegacyMap::date($client->reminder) : null;

            if ($target['open'] && $reminder !== null && $reminder > today()->toDateString()) {
                $rows[] = [
                    'activity_type_id' => $followUpType,
                    'title' => 'Follow up (reminder from v1)',
                    'scheduled_at' => Carbon::parse($reminder.' 10:00:00'),
                    'owner_user_id' => $target['owner'],
                ];
            }

            foreach ($rows as $row) {
                $activity = new CrmActivity;
                $activity->forceFill([...$row, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'created_at' => $row['completed_at'] ?? now()])->save();
                $created++;
            }

            if ($rows !== [] && $target['customer'] === null) {
                $this->followUps->refresh(Lead::query()->findOrFail($target['lead']));
            }
        }

        return $created;
    }
}
