<?php

use App\Modules\Crm\Actions\MergeCustomers;
use App\Modules\Crm\Events\CustomersMerging;
use App\Modules\Crm\Livewire\Customers\Merge;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Foundation\Models\AuditLog;
use App\Modules\Foundation\Models\Note;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    seedCrm();
    $this->admin = userWithPermissions('crm.customers.view_all', 'crm.customers.merge');
    $this->survivor = Customer::factory()->create(['name' => 'Rahim Uddin']);
    $this->survivor->contacts()->create(['name' => 'Primary', 'is_primary' => true]);
    $this->duplicate = Customer::factory()->create(['name' => 'Rahim Uddin (dup)']);

    $this->converted = Lead::factory()->converted($this->duplicate)->create();
    $this->referred = Lead::factory()->create(['referrer_type' => 'customer', 'referrer_id' => $this->duplicate->id]);
    CrmActivity::factory()->on($this->duplicate)->create();
    $this->duplicate->contacts()->create(['name' => 'Other primary', 'is_primary' => true]);
    $this->duplicate->notes()->create(['body' => 'Met at fair', 'created_by' => $this->admin->id]);
});

test('the preview counts what will move', function () {
    expect(app(MergeCustomers::class)->preview($this->duplicate))->toMatchArray([
        'leads' => 1, 'referred_leads' => 1, 'activities' => 1, 'contacts' => 1, 'notes' => 1, 'attachments' => 0,
    ]);
});

test('merging moves everything, keeps one primary contact and soft-deletes the duplicate (CRM-AC-10 CRM part)', function () {
    Event::fake([CustomersMerging::class]);

    app(MergeCustomers::class)->handle($this->admin, $this->survivor, $this->duplicate, 'Same person');

    $duplicate = Customer::withTrashed()->find($this->duplicate->id);

    expect($this->converted->fresh()->converted_customer_id)->toBe($this->survivor->id)
        ->and($this->referred->fresh()->referrer_id)->toBe($this->survivor->id)
        ->and($this->survivor->activities()->count())->toBe(1)
        ->and($this->survivor->contacts()->where('is_primary', true)->count())->toBe(1)
        ->and($this->survivor->contacts()->count())->toBe(2)
        ->and(Note::query()->where('notable_type', 'customer')->where('notable_id', $this->survivor->id)->count())->toBe(1)
        ->and($duplicate->trashed())->toBeTrue()
        ->and($duplicate->merged_into_id)->toBe($this->survivor->id)
        ->and(AuditLog::query()->where('auditable_id', $this->survivor->id)->where('event', 'merged')->count())->toBeGreaterThanOrEqual(5);

    Event::assertDispatched(CustomersMerging::class, fn ($event) => $event->survivor->is($this->survivor) && $event->duplicate->is($this->duplicate));
    $this->actingAs($this->admin)->get(route('crm.customers.show', $this->duplicate->customer_number))->assertNotFound();
});

test('a reason, two different customers and the merge permission are required', function () {
    expectValidationError(fn () => app(MergeCustomers::class)->handle($this->admin, $this->survivor, $this->duplicate, ''), 'reason');
    expectValidationError(fn () => app(MergeCustomers::class)->handle($this->admin, $this->survivor, $this->survivor, 'x'), 'duplicate');

    expect(fn () => app(MergeCustomers::class)->handle(userWithPermissions('crm.customers.view_all'), $this->survivor, $this->duplicate, 'x'))
        ->toThrow(AuthorizationException::class);
});

test('the merge screen previews, swaps and merges', function () {
    Livewire::actingAs($this->admin)->test(Merge::class, ['customer' => $this->survivor])
        ->set('duplicateId', (string) $this->duplicate->id)
        ->assertSet('preview.leads', 1)
        ->call('swap')
        ->assertSet('survivorId', $this->duplicate->id)
        ->call('swap')
        ->set('reason', 'Same person')
        ->call('merge')
        ->assertRedirect(route('crm.customers.show', $this->survivor));
});
