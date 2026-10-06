<?php

use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use Database\Seeders\Catalog\CatalogSeeder;
use Database\Seeders\Foundation\LocationSeeder;
use Database\Seeders\Legacy\ImportClients;
use Database\Seeders\Legacy\ImportSalesTeams;
use Database\Seeders\Legacy\ImportUsers;
use Database\Seeders\Legacy\LegacyContext;

beforeEach(function () {
    useLegacyDatabase();
    seedCrm();
    seedAccessControl();
    $this->seed([CatalogSeeder::class, LocationSeeder::class]);
    $this->admin = superAdmin(['username' => 'admin']);

    legacyRow('tbl_user', ['id' => 16, 'name' => 'MSD', 'user_name' => 'msd', 'type' => 't', 'team_name' => 'a', 'status' => 'a']);
    legacyRow('tbl_user', ['id' => 4, 'name' => 'Delower', 'user_name' => 'delower', 'type' => 'u', 'team_name' => 'a', 'status' => 'a']);

    $this->client = fn (array $row) => legacyRow('tbl_client', [
        'client_id' => 'SOC-CON-000'.$row['id'], 'client_name' => 'Client '.$row['id'], 'phone' => '0171100'.str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT),
        'date' => '0000-00-00', 'level' => 'Entry', 'source' => 'F', 'status' => 'p', 'add_by' => 16, 'add_time' => '2024-01-10 10:30:00', ...$row,
    ]);
});

function importClients(): LegacyContext
{
    $context = new LegacyContext;
    app(ImportUsers::class)->run($context);
    app(ImportSalesTeams::class)->run($context);
    app(ImportClients::class)->run($context);

    return $context;
}

test('a pending client with history becomes a contacted lead with its mapped fields', function () {
    ($this->client)(['id' => 12, 'client_id' => 'SOC-CON-00012', 'client_name' => 'Mokbul Hossain', 'org_name' => '', 'phone' => '+880 1711-223344', 'w_number' => '01711223344', 'email' => 'MOKBUL@example.com', 'org_mobile' => 'n/a', 'area_id' => 18, 'requirement' => '1,2', 'level' => 'Middle', 'comment' => 'Called, wants design', 'note' => '6 storied building', 'add_by' => 4]);
    ($this->client)(['id' => 13, 'add_by' => 999, 'area_id' => 2, 'requirement' => '']);

    $context = importClients();

    $lead = Lead::query()->where('legacy_client_ref', 12)->with(['status', 'source', 'level', 'businessLine', 'location', 'services.service', 'assignee', 'team'])->sole();
    $plain = Lead::query()->where('legacy_client_ref', 13)->with(['status', 'assignee'])->sole();

    expect($lead)
        ->lead_number->toBe('L-000001')->name->toBe('Mokbul Hossain')->company_name->toBeNull()
        ->phone->toBe('01711223344')->whatsapp->toBe('01711223344')->office_phone->toBeNull()->email->toBe('mokbul@example.com')
        ->legacy_client_id->toBe('SOC-CON-00012')->notes->toBe('6 storied building')
        ->and($lead->lead_date->toDateString())->toBe('2024-01-10')
        ->and($lead->created_at->toDateTimeString())->toBe('2024-01-10 10:30:00')
        ->and($lead->status->code)->toBe('CONTACTED')
        ->and($lead->source->code)->toBe('REFERENCE')
        ->and($lead->level->code)->toBe('MID')
        ->and($lead->businessLine->code)->toBe('CON')
        ->and($lead->location->name)->toBe('Banasree')
        ->and($lead->services->pluck('service.code')->sort()->values()->all())->toBe(['BD', 'BDRA'])
        ->and($lead->assignee->username)->toBe('delower')
        ->and($lead->team->name)->toBe('Team A')
        ->and($lead->statusHistories()->count())->toBe(1);

    expect($plain)->lead_number->toBe('L-000002')->location_id->toBeNull()
        ->and($plain->status->code)->toBe('NEW')
        ->and($plain->assignee->id)->toBe($this->admin->id)
        ->and($context->leads[13]['open'])->toBeTrue();
});

test('a sold client becomes a won lead and a customer with attribution', function () {
    ($this->client)(['id' => 20, 'client_id' => 'SOC-CETP-0020', 'client_name' => 'Rahim Uddin', 'org_name' => 'Rahim Builders', 'status' => 's', 'source' => 'FB', 'add_time' => '2024-05-02 16:00:00']);

    $context = importClients();

    $lead = Lead::query()->where('legacy_client_ref', 20)->with(['status', 'convertedCustomer.type', 'businessLine'])->sole();
    $customer = $lead->convertedCustomer;

    expect($lead->status->code)->toBe('WON')
        ->and($lead->won_at->toDateTimeString())->toBe('2024-05-02 16:00:00')
        ->and($lead->businessLine->code)->toBe('CETP')
        ->and($customer)->customer_number->toBe('C-000001')->name->toBe('Rahim Uddin')->company_name->toBe('Rahim Builders')
        ->legacy_client_id->toBe('SOC-CETP-0020')->legacy_client_ref->toBe(20)
        ->source_lead_id->toBe($lead->id)->lead_source_id->toBe($lead->lead_source_id)
        ->account_manager_user_id->toBe($lead->assigned_to)->acquired_by_user_id->toBe($lead->assigned_to)
        ->and($customer->type->code)->toBe('COMPANY')
        ->and($context->leads[20])->toMatchArray(['customer' => $customer->id, 'open' => false]);
});

test('deleted clients are skipped, numbers follow v1 order and re-running adds nothing', function () {
    ($this->client)(['id' => 3]);
    ($this->client)(['id' => 1]);
    ($this->client)(['id' => 2, 'status' => 'd']);

    importClients();
    importClients();

    expect(Lead::query()->orderBy('lead_number')->pluck('legacy_client_ref')->all())->toBe([1, 3])
        ->and(Customer::query()->count())->toBe(0);
});
