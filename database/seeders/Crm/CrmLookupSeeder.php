<?php

namespace Database\Seeders\Crm;

use App\Modules\Crm\Models\ActivityOutcome;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\CustomerStatus;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\LeadLevel;
use App\Modules\Crm\Models\LeadPriority;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Models\LostReason;
use App\Modules\Crm\Models\PaymentTerm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * CRM lookups (docs/03 §3.1). Existing rows keep admin edits; only missing rows are created.
 */
class CrmLookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed(LeadSource::class, [
            ['LEAFLET', 'Leaflet'], ['F2F', 'Face to Face'], ['FACEBOOK', 'Facebook'], ['GOOGLE', 'Google'],
            ['YOUTUBE', 'YouTube'], ['REFERENCE', 'Friend & Reference', ['requires_referrer' => true]],
            ['WEBSITE', 'Website'], ['PHONE', 'Phone Call'], ['WHATSAPP', 'WhatsApp'], ['WALKIN', 'Walk-in'],
            ['EXISTING', 'Existing Customer', ['requires_referrer' => true, 'is_system' => true]],
            ['AGENT', 'Agent', ['requires_referrer' => true]], ['OTHER', 'Other'],
        ]);

        $this->seed(LeadStatus::class, [
            ['NEW', 'New', ['probability_pct' => 10, 'is_system' => true]],
            ['CONTACTED', 'Contacted', ['probability_pct' => 20]],
            ['QUALIFIED', 'Qualified', ['probability_pct' => 30]],
            ['MEETING', 'Meeting', ['probability_pct' => 40]],
            ['SITE_VISIT', 'Site Visit', ['probability_pct' => 50]],
            ['PROPOSAL', 'Proposal Sent', ['probability_pct' => 60]],
            ['NEGOTIATION', 'Negotiation', ['probability_pct' => 75]],
            ['WON', 'Won', ['probability_pct' => 100, 'is_won' => true, 'is_closed' => true, 'is_system' => true, 'color' => 'success']],
            ['LOST', 'Lost', ['probability_pct' => 0, 'is_lost' => true, 'is_closed' => true, 'is_system' => true, 'color' => 'danger']],
        ]);

        $this->seed(LeadPriority::class, [['LOW', 'Low'], ['NORMAL', 'Normal', ['is_system' => true]], ['HIGH', 'High'], ['URGENT', 'Urgent']]);
        $this->seed(LeadLevel::class, [['ENTRY', 'Entry Level'], ['MID', 'Mid Level'], ['TOP', 'Top Level']]);
        $this->seed(LostReason::class, $this->named(['Price too high', 'Chose competitor', 'Project postponed', 'No response', 'Not qualified / no budget', 'Land/legal issue', 'Other']));

        $this->seed(ActivityType::class, [
            ['CALL', 'Call', ['icon' => 'phone', 'counts_as_contact' => true]],
            ['MEETING', 'Meeting', ['icon' => 'users', 'counts_as_contact' => true, 'requires_duration' => true]],
            ['EMAIL', 'Email', ['icon' => 'mail']],
            ['WHATSAPP', 'WhatsApp', ['icon' => 'message-circle']],
            ['SMS', 'SMS', ['icon' => 'message-square']],
            ['SITE_VISIT', 'Site Visit', ['icon' => 'map-pin', 'counts_as_contact' => true, 'requires_duration' => true]],
            ['OFFICE_VISIT', 'Office Visit', ['icon' => 'building', 'counts_as_contact' => true, 'requires_duration' => true]],
            ['NOTE', 'Note', ['icon' => 'sticky-note']],
            ['FOLLOW_UP', 'Follow-up', ['icon' => 'calendar-clock']],
        ]);

        $this->seed(ActivityOutcome::class, $this->named(['Interested', 'Not interested', 'Call back', 'No answer', 'Wrong number', 'Meeting fixed', 'Proposal requested']));
        $this->seed(CustomerType::class, [['INDIVIDUAL', 'Individual'], ['COMPANY', 'Company'], ['ORGANIZATION', 'Organization'], ['GOVERNMENT', 'Government'], ['BANK', 'Bank'], ['NGO', 'NGO'], ['OTHER', 'Other']]);
        $this->seed(CustomerStatus::class, [
            ['ACTIVE', 'Active', ['is_system' => true, 'color' => 'success']],
            ['INACTIVE', 'Inactive', ['is_system' => true]],
            ['BLOCKED', 'Blocked', ['is_blocked' => true, 'is_system' => true, 'color' => 'danger']],
        ]);
        $this->seed(PaymentTerm::class, [['IMMEDIATE', 'Immediate', ['days' => 0]], ['NET7', 'Net 7', ['days' => 7]], ['NET15', 'Net 15', ['days' => 15]], ['NET30', 'Net 30', ['days' => 30]], ['MILESTONE', 'Milestone', ['days' => 0]]]);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<array{0: string, 1: string, 2?: array<string, mixed>}>  $rows
     */
    private function seed(string $model, array $rows): void
    {
        foreach ($rows as $index => $row) {
            $model::query()->firstOrCreate(['code' => $row[0]], ['name' => $row[1], 'sort_order' => $index + 1, ...($row[2] ?? [])]);
        }
    }

    /**
     * Rows whose code is derived from the name (upper snake case).
     *
     * @param  list<string>  $names
     * @return list<array{0: string, 1: string}>
     */
    private function named(array $names): array
    {
        return array_map(fn (string $name): array => [Str::upper(Str::snake(Str::of($name)->replaceMatches('/[^A-Za-z ]/', ' ')->squish()->value())), $name], $names);
    }
}
