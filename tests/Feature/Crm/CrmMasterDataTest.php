<?php

use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Foundation\Livewire\Admin\MasterData;
use App\Modules\Foundation\Services\Navigation;
use App\Support\Lookups\LookupRegistry;
use Livewire\Livewire;

beforeEach(fn () => seedCrm());

test('the crm lookups are registered under crm.master_data', function () {
    $tables = collect(app(LookupRegistry::class)->all())->where('module', 'crm');

    expect($tables->keys()->all())->toEqualCanonicalizing([
        'lead_sources', 'lead_statuses', 'lead_priorities', 'lead_levels', 'lost_reasons',
        'activity_types', 'activity_outcomes', 'customer_types', 'customer_statuses', 'payment_terms',
    ])->and($tables->pluck('permission')->unique()->all())->toBe(['crm.master_data']);
});

test('status flags are not editable on the master data screen', function () {
    $fields = app(LookupRegistry::class)->get('lead_statuses')['extra_fields'];

    expect($fields)->toHaveKey('probability_pct')
        ->and($fields)->not->toHaveKeys(['is_won', 'is_lost', 'is_closed'])
        ->and(app(LookupRegistry::class)->get('customer_statuses')['extra_fields'])->not->toHaveKey('is_blocked');
});

test('the master data screen needs crm.master_data.view', function () {
    $this->actingAs(userWithPermissions('crm.leads.view_own'));
    $this->get(route('admin.master-data.show', 'lead_sources'))->assertForbidden();

    $this->actingAs(userWithPermissions('crm.master_data.view'));
    $this->get(route('admin.master-data.show', 'lead_sources'))->assertOk()->assertSee('Face to Face');
});

test('the probability must be between 0 and 100', function () {
    $status = LeadStatus::query()->where('code', 'MEETING')->firstOrFail();

    Livewire::actingAs(userWithPermissions('crm.master_data.view', 'crm.master_data.update'))
        ->test(MasterData::class, ['table' => 'lead_statuses'])
        ->call('edit', $status->id)
        ->set('form.probability_pct', '120')
        ->call('save')
        ->assertHasErrors('form.probability_pct');
});

test('seeded system statuses can be saved unchanged from the master data screen', function (string $code) {
    $status = LeadStatus::query()->where('code', $code)->firstOrFail();

    Livewire::actingAs(userWithPermissions('crm.master_data.view', 'crm.master_data.update'))
        ->test(MasterData::class, ['table' => 'lead_statuses'])
        ->call('edit', $status->id)
        ->call('save')
        ->assertHasNoErrors();
})->with(['MEETING', 'LOST']);

test('the crm setup tree shows for master data viewers', function () {
    $groups = app(Navigation::class)->for(userWithPermissions('crm.master_data.view'));
    $crm = collect($groups)->firstWhere('key', 'crm');

    expect(collect($crm['items'])->firstWhere('label', 'CRM setup')['children'])->toHaveCount(10);
});
