<?php

use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Lead;
use Database\Seeders\Catalog\CatalogSeeder;
use Database\Seeders\Legacy\ImportClientActivities;
use Database\Seeders\Legacy\ImportClients;
use Database\Seeders\Legacy\ImportSalesTeams;
use Database\Seeders\Legacy\ImportUsers;
use Database\Seeders\Legacy\LegacyContext;
use Illuminate\Support\Carbon;

beforeEach(function () {
    useLegacyDatabase();
    seedCrm();
    seedAccessControl();
    $this->seed(CatalogSeeder::class);
    superAdmin(['username' => 'admin']);
    Carbon::setTestNow('2026-10-06 12:00:00');

    legacyRow('tbl_user', ['id' => 16, 'name' => 'MSD', 'user_name' => 'MSD', 'type' => 't', 'team_name' => 'a', 'status' => 'a']);
    legacyRow('tbl_user', ['id' => 21, 'name' => 'Robin', 'user_name' => 'robin', 'type' => 'u', 'team_name' => 'a', 'status' => 'a']);

    legacyRow('tbl_client', ['id' => 1, 'client_id' => 'SOC-CON-0001', 'client_name' => 'Open Client', 'phone' => '01711000001', 'status' => 'p', 'add_by' => 16, 'add_time' => '2025-01-05 10:00:00', 'comment' => 'Wants a 6 storey design', 'reminder' => '0027-03-15']);
    legacyRow('tbl_client', ['id' => 2, 'client_id' => 'SOC-CON-0002', 'client_name' => 'Sold Client', 'phone' => '01711000002', 'status' => 's', 'add_by' => 16, 'add_time' => '2024-02-01 10:00:00', 'reminder' => '2027-01-01']);
    legacyRow('tbl_client', ['id' => 3, 'client_id' => 'SOC-CON-0003', 'client_name' => 'Old Client', 'phone' => '01711000003', 'status' => 'p', 'add_by' => 16, 'add_time' => '2024-02-01 10:00:00', 'reminder' => '2024-03-01']);

    legacyRow('tbl_clientdetails', ['id' => 1, 'client_id' => 1, 'note' => 'Called, asked for the land papers and a site visit next week.', 'added_by' => 'robin', 'added_date' => '2025-02-10 15:20:00']);
    legacyRow('tbl_clientdetails', ['id' => 2, 'client_id' => 1, 'note' => 'Sent Eid leaflet', 'added_by' => 'soc', 'added_date' => '2025-03-28 11:00:00']);
    legacyRow('tbl_clientdetails', ['id' => 3, 'client_id' => 2, 'note' => 'Agreement signed', 'added_by' => 'msd', 'added_date' => '2024-02-05 12:00:00']);
});

afterEach(fn () => Carbon::setTestNow());

function importActivities(): void
{
    $context = new LegacyContext;
    app(ImportUsers::class)->run($context);
    app(ImportSalesTeams::class)->run($context);
    app(ImportClients::class)->run($context);
    app(ImportClientActivities::class)->run($context);
}

test('follow-up notes, comments and future reminders become activities', function () {
    importActivities();

    $open = Lead::query()->where('legacy_client_ref', 1)->sole();
    $activities = CrmActivity::query()->where('subject_type', 'lead')->where('subject_id', $open->id)->with(['type', 'owner'])->orderBy('id')->get();
    $done = $activities->whereNotNull('completed_at')->values();
    $followUp = $activities->whereNull('completed_at')->sole();

    expect($done)->toHaveCount(3)
        ->and($done->pluck('type.code')->unique()->all())->toBe(['NOTE'])
        ->and($done->firstWhere('description', 'Called, asked for the land papers and a site visit next week.'))
        ->owner->username->toBe('robin')
        ->completed_at->toDateTimeString()->toBe('2025-02-10 15:20:00')
        ->and($done->firstWhere('description', 'Sent Eid leaflet')->owner->username)->toBe('msd')
        ->and($done->firstWhere('title', 'Legacy comments')->description)->toBe('Wants a 6 storey design')
        ->and($followUp->type->code)->toBe('FOLLOW_UP')
        ->and($followUp->scheduled_at->toDateTimeString())->toBe('2027-03-15 10:00:00')
        ->and($open->fresh()->next_follow_up_at->toDateTimeString())->toBe('2027-03-15 10:00:00')
        ->and($open->fresh()->last_activity_at->toDateTimeString())->toBe('2025-03-28 11:00:00');
});

test('won leads keep their notes on the customer, and past reminders are dropped', function () {
    importActivities();

    $sold = Lead::query()->where('legacy_client_ref', 2)->sole();
    $old = Lead::query()->where('legacy_client_ref', 3)->sole();

    expect(CrmActivity::query()->where('subject_type', 'customer')->where('subject_id', $sold->converted_customer_id)->pluck('description')->all())->toBe(['Agreement signed'])
        ->and(CrmActivity::query()->whereNull('completed_at')->count())->toBe(1)
        ->and(CrmActivity::query()->where('subject_type', 'lead')->where('subject_id', $old->id)->count())->toBe(0);
});

test('re-running adds nothing', function () {
    importActivities();
    $count = CrmActivity::query()->count();

    importActivities();

    expect(CrmActivity::query()->count())->toBe($count);
});
