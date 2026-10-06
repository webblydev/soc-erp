<?php

use App\Models\User;
use App\Modules\Crm\Actions\SaveCustomer;
use App\Modules\Crm\Events\CustomerCreated;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerStatus;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\PaymentTerm;
use App\Modules\Foundation\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    seedCrm();
    $this->actor = userWithPermissions('crm.customers.view_all', 'crm.customers.create', 'crm.customers.update');
});

function customerInput(array $overrides = []): array
{
    return [
        'customer_type_id' => CustomerType::idFor('INDIVIDUAL'), 'name' => 'Rahim Uddin', 'company_name' => null,
        'phone' => '+880 1711-000000', 'alternate_phone' => null, 'whatsapp' => null, 'email' => 'Rahim@Example.com',
        'address' => null, 'location_id' => null, 'nid_or_reg_no' => null, 'business_line_id' => null,
        'account_manager_user_id' => null, 'customer_status_id' => null, 'is_also_vendor' => false, 'notes' => null,
        'contacts' => [], 'duplicate_reason' => null,
        ...$overrides,
    ];
}

test('a customer is created with a number, an active status and normalised contacts', function () {
    Event::fake([CustomerCreated::class]);

    $customer = app(SaveCustomer::class)->handle($this->actor, customerInput(['contacts' => [
        ['name' => 'Karim', 'designation' => 'Son', 'phone' => '01811 000000', 'email' => null, 'is_primary' => true, 'notes' => null],
    ]]));

    expect($customer->customer_number)->toBe('C-000001')
        ->and($customer->phone)->toBe('01711000000')
        ->and($customer->email)->toBe('rahim@example.com')
        ->and($customer->customer_status_id)->toBe(CustomerStatus::idFor('ACTIVE'))
        ->and($customer->contacts()->first())->phone->toBe('01811000000')->is_primary->toBeTrue();

    Event::assertDispatched(CustomerCreated::class, fn ($event) => $event->customer->is($customer));
});

test('finance fields need crm.customers.update_finance (spec R15)', function () {
    $term = PaymentTerm::query()->where('code', 'NET30')->firstOrFail();
    $finance = ['payment_term_id' => $term->id, 'credit_limit' => '100000', 'tin' => '123456789012', 'bin' => null];

    $plain = app(SaveCustomer::class)->handle($this->actor, customerInput($finance));

    expect($plain->payment_term_id)->toBeNull()->and($plain->credit_limit)->toBeNull();

    $accountant = userWithPermissions('crm.customers.view_all', 'crm.customers.update', 'crm.customers.update_finance');
    $updated = app(SaveCustomer::class)->handle($accountant, customerInput([...$finance, 'phone' => '01711000000', 'duplicate_reason' => null]), $plain);

    expect($updated->payment_term_id)->toBe($term->id)->and($updated->credit_limit)->toBe('100000.00')->and($updated->tin)->toBe('123456789012');
});

test('a phone used by another customer needs a reason, which is audited (CRM-BR-12)', function () {
    $existing = Customer::factory()->create(['alternate_phone' => '01711000000']);

    expectValidationError(fn () => app(SaveCustomer::class)->handle($this->actor, customerInput()), 'duplicate_reason');

    $customer = app(SaveCustomer::class)->handle($this->actor, customerInput(['duplicate_reason' => 'Shared family number']));

    expect(AuditLog::query()->where('auditable_id', $customer->id)->where('event', 'duplicate_override')->first()->new_values)
        ->toMatchArray(['reason' => 'Shared family number', 'matches' => [$existing->customer_number]]);
});

test('editing a customer whose phones did not change asks for no new reason', function () {
    Customer::factory()->create(['phone' => '01711000000']);
    $customer = app(SaveCustomer::class)->handle($this->actor, customerInput(['duplicate_reason' => 'Shared family number']));

    expect(app(SaveCustomer::class)->handle($this->actor, customerInput(['name' => 'Rahim U.']), $customer)->name)->toBe('Rahim U.');
});

test('saving the same customer again is not a duplicate of itself', function () {
    $customer = app(SaveCustomer::class)->handle($this->actor, customerInput());

    expect(app(SaveCustomer::class)->handle($this->actor, customerInput(['name' => 'Rahim U.']), $customer)->name)->toBe('Rahim U.');
});

test('phones, one primary contact and active lookups are validated', function () {
    expectValidationError(fn () => app(SaveCustomer::class)->handle($this->actor, customerInput(['phone' => '12345'])), 'phone');
    expectValidationError(fn () => app(SaveCustomer::class)->handle($this->actor, customerInput(['contacts' => [
        ['name' => 'A', 'is_primary' => true], ['name' => 'B', 'is_primary' => true],
    ]])), 'contacts');
    expectValidationError(fn () => app(SaveCustomer::class)->handle($this->actor, customerInput(['customer_type_id' => CustomerType::factory()->create(['is_active' => false])->id])), 'customer_type_id');
});

test('contacts are updated, added and removed', function () {
    $customer = app(SaveCustomer::class)->handle($this->actor, customerInput(['contacts' => [['name' => 'Old', 'is_primary' => true]]]));
    $old = $customer->contacts()->first();

    app(SaveCustomer::class)->handle($this->actor, customerInput(['contacts' => [
        ['id' => $old->id, 'name' => 'Old renamed', 'is_primary' => false],
        ['name' => 'New', 'is_primary' => true],
    ]]), $customer);

    expect($customer->contacts()->pluck('name')->all())->toEqualCanonicalizing(['Old renamed', 'New']);

    app(SaveCustomer::class)->handle($this->actor, customerInput(['contacts' => []]), $customer);

    expect($customer->contacts()->count())->toBe(0);
});

test('a contact id from another customer is refused', function () {
    $other = Customer::factory()->create();
    $foreign = $other->contacts()->create(['name' => 'Foreign']);
    $customer = app(SaveCustomer::class)->handle($this->actor, customerInput());

    expectValidationError(fn () => app(SaveCustomer::class)->handle($this->actor, customerInput(['contacts' => [['id' => $foreign->id, 'name' => 'Hijack']]]), $customer), 'contacts.0.id');
});

test('creating needs crm.customers.create and editing needs update and scope', function () {
    $owner = User::factory()->create();
    $customer = Customer::factory()->managedBy($owner)->create();
    $executive = userWithPermissions('crm.customers.view_own', 'crm.customers.update');

    expect(fn () => app(SaveCustomer::class)->handle($executive, customerInput(['phone' => '01999000000']), $customer))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveCustomer::class)->handle(userWithPermissions('crm.customers.view_all'), customerInput()))
        ->toThrow(AuthorizationException::class);
});
