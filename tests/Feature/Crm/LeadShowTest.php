<?php

use App\Models\User;
use App\Modules\Crm\Livewire\Leads\Show;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Foundation\Livewire\Shared\Attachments;
use App\Modules\Foundation\Livewire\Shared\History;
use App\Modules\Foundation\Livewire\Shared\Notes;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedCrm();
    Notification::fake();
    $this->owner = userWithPermissions('crm.leads.view_own', 'crm.leads.update', 'crm.activities.view_own', 'crm.activities.create', 'crm.activities.update');
    $this->lead = Lead::factory()->assignedTo($this->owner)->create(['name' => 'Rahim Uddin']);
});

test('the detail page and print need the lead to be visible', function () {
    $this->actingAs($this->owner);
    $this->get(route('crm.leads.show', $this->lead))->assertOk()->assertSee('Rahim Uddin')->assertSee($this->lead->lead_number);
    $this->get(route('crm.leads.print', $this->lead))->assertOk()->assertSee($this->lead->lead_number);

    $this->actingAs(userWithPermissions('crm.leads.view_own'));
    $this->get(route('crm.leads.show', $this->lead))->assertForbidden();
    $this->get(route('crm.leads.print', $this->lead))->assertForbidden();
});

test('tabs show activities, documents, notes and history', function () {
    CrmActivity::factory()->on($this->lead)->create(['title' => 'Call about plot', 'owner_user_id' => $this->owner->id]);

    $component = Livewire::actingAs($this->owner)->test(Show::class, ['lead' => $this->lead]);

    $component->set('tab', 'activities')->assertSee('Call about plot');
    $component->set('tab', 'documents')->assertSeeLivewire(Attachments::class);
    $component->set('tab', 'notes')->assertSeeLivewire(Notes::class);
    $component->set('tab', 'history')->assertSeeLivewire(History::class);
});

test('header actions follow permissions and state', function () {
    Livewire::actingAs($this->owner)->test(Show::class, ['lead' => $this->lead])
        ->assertSee(__('Edit'))->assertSee(__('Log activity'))->assertDontSeeHtml('open-sheet-lead-assign');

    $converted = Lead::factory()->assignedTo($this->owner)->converted(Customer::factory()->create(['name' => 'Rahim Holdings']))->create();

    Livewire::actingAs($this->owner)->test(Show::class, ['lead' => $converted])
        ->assertDontSee(route('crm.leads.edit', $converted))
        ->assertSee(__('Log activity'))
        ->assertSee('Rahim Holdings');
});

test('a manager assigns from the detail page', function () {
    $manager = userWithPermissions('crm.leads.view_all', 'crm.leads.assign');
    $other = User::factory()->create();
    $other->syncDirectPermissions(['crm.leads.view_own']);

    Livewire::actingAs($manager)->test(Show::class, ['lead' => $this->lead])
        ->set('assignTo', (string) $other->id)
        ->set('assignReason', 'Workload')
        ->call('assign')
        ->assertHasNoErrors();

    expect($this->lead->fresh()->assigned_to)->toBe($other->id);
});

test('the lead number in the url is the route key', function () {
    expect(route('crm.leads.show', $this->lead))->toEndWith('/crm/leads/'.$this->lead->lead_number);
});
