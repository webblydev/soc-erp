<?php

use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\CustomerStatus;
use App\Modules\Crm\Models\LeadSource;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Models\PaymentTerm;
use App\Support\Facades\Settings;
use Database\Seeders\Crm\CrmSeeder;

test('the crm seeder fills the lookups of docs/03 §3.1', function () {
    $this->seed(CrmSeeder::class);

    expect(LeadSource::query()->count())->toBe(13)
        ->and(LeadSource::query()->where('requires_referrer', true)->pluck('code')->all())->toEqualCanonicalizing(['REFERENCE', 'EXISTING', 'AGENT'])
        ->and(LeadStatus::query()->ordered()->pluck('code')->all())->toBe(['NEW', 'CONTACTED', 'QUALIFIED', 'MEETING', 'SITE_VISIT', 'PROPOSAL', 'NEGOTIATION', 'WON', 'LOST'])
        ->and(LeadStatus::query()->where('code', 'WON')->first())->is_won->toBeTrue()->is_closed->toBeTrue()->is_system->toBeTrue()
        ->and(LeadStatus::query()->where('code', 'NEGOTIATION')->value('probability_pct'))->toBe(75)
        ->and(LeadStatus::query()->open()->count())->toBe(7)
        ->and(ActivityType::query()->where('counts_as_contact', true)->pluck('code')->all())->toEqualCanonicalizing(['CALL', 'MEETING', 'SITE_VISIT', 'OFFICE_VISIT'])
        ->and(ActivityType::query()->where('code', 'MEETING')->value('icon'))->toBe('users')
        ->and(CustomerStatus::query()->where('is_blocked', true)->pluck('code')->all())->toBe(['BLOCKED'])
        ->and(PaymentTerm::query()->where('code', 'NET30')->value('days'))->toBe(30);
});

test('the crm seeder adds the crm settings', function () {
    $this->seed(CrmSeeder::class);

    expect(Settings::get('crm.duplicate_check_fields'))->toBe(['phone', 'whatsapp', 'email'])
        ->and(Settings::get('crm.auto_assign_mode'))->toBe('none')
        ->and(Settings::get('crm.stale_lead_days'))->toBe(14)
        ->and(Settings::get('crm.lost_reason_required'))->toBeTrue()
        ->and(Settings::get('crm.allow_convert_without_project'))->toBeFalse();
});

test('the crm seeder is idempotent and keeps admin edits', function () {
    $this->seed(CrmSeeder::class);
    LeadSource::query()->where('code', 'LEAFLET')->update(['name' => 'Flyer']);

    $this->seed(CrmSeeder::class);

    expect(LeadSource::query()->count())->toBe(13)
        ->and(LeadSource::query()->where('code', 'LEAFLET')->value('name'))->toBe('Flyer');
});
