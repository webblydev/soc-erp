<?php

use App\Modules\Crm\Actions\FindDuplicates;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Support\Facades\Settings;

beforeEach(fn () => seedCrm());

test('a phone matches any phone column of open leads and customers', function () {
    $lead = Lead::factory()->create(['phone' => '01711000001', 'whatsapp' => '01811000001']);
    $customer = Customer::factory()->create(['phone' => '01911000001', 'alternate_phone' => '01711000002']);

    $matches = app(FindDuplicates::class)->handle(['phone' => '+880 1811-000001', 'whatsapp' => '01711000002']);

    expect(collect($matches)->map(fn ($match) => $match->type.':'.$match->id)->all())
        ->toEqualCanonicalizing(['lead:'.$lead->id, 'customer:'.$customer->id]);
});

test('closed leads, merged customers and the record itself are ignored', function () {
    Lead::factory()->withStatus('LOST')->create(['phone' => '01711000003']);
    $survivor = Customer::factory()->create();
    Customer::factory()->create(['phone' => '01711000003', 'merged_into_id' => $survivor->id]);

    expect(app(FindDuplicates::class)->handle(['phone' => '01711000003']))->toBe([]);

    $open = Lead::factory()->create(['phone' => '01711000004']);

    expect(app(FindDuplicates::class)->handle(['phone' => '01711000004'], ignoreLead: $open))->toBe([]);
});

test('emails match case-insensitively and only when the setting lists email', function () {
    Customer::factory()->create(['email' => 'rahim@example.com']);

    expect(app(FindDuplicates::class)->handle(['email' => 'Rahim@Example.com']))->toHaveCount(1);

    Settings::set('crm.duplicate_check_fields', ['phone']);

    expect(app(FindDuplicates::class)->handle(['email' => 'rahim@example.com']))->toBe([]);
});
