<?php

use App\Models\User;
use App\Modules\Crm\Livewire\Activities\Index;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Lead;
use Livewire\Livewire;

beforeEach(function () {
    seedCrm();
    $this->travelTo(now()->setDateTime(2026, 10, 7, 12, 0));
    $this->me = userWithPermissions('crm.leads.view_own', 'crm.activities.view_own', 'crm.activities.update');
    $lead = Lead::factory()->assignedTo($this->me)->create(['name' => 'Rahim Uddin']);

    $make = fn (string $title, array $attributes) => CrmActivity::factory()->on($lead)->create(['title' => $title, 'owner_user_id' => $this->me->id, ...$attributes]);
    $make('Overdue call', ['scheduled_at' => now()->subDay()]);
    $make('Today meeting', ['scheduled_at' => now()->addHours(3)]);
    $make('Next week visit', ['scheduled_at' => now()->addDays(6)]);
    $make('Done call', ['scheduled_at' => null, 'completed_at' => now()->subHour()]);
    CrmActivity::factory()->on($lead)->create(['title' => 'Someone else', 'owner_user_id' => User::factory()->create()->id, 'scheduled_at' => now()->addHour()]);
});

test('the screen needs an activities view permission', function () {
    $this->actingAs(userWithPermissions('crm.leads.view_own'));
    $this->get(route('crm.activities.index'))->assertForbidden();

    $this->actingAs($this->me);
    $this->get(route('crm.activities.index'))->assertOk();
});

test('each tab shows its activities, linking the subject', function (string $tab, string $see) {
    Livewire::actingAs($this->me)->test(Index::class)
        ->set('tab', $tab)
        ->assertSee($see)
        ->assertSee('Rahim Uddin')
        ->assertDontSee('Someone else');
})->with([
    ['overdue', 'Overdue call'],
    ['today', 'Today meeting'],
    ['upcoming', 'Next week visit'],
    ['done', 'Done call'],
]);

test('tabs do not leak into each other', function () {
    Livewire::actingAs($this->me)->test(Index::class)
        ->set('tab', 'today')->assertDontSee('Overdue call')->assertDontSee('Next week visit')->assertDontSee('Done call');
});

test('an owner outside the scope is ignored', function () {
    $other = User::factory()->create();

    Livewire::actingAs($this->me)->test(Index::class)
        ->set('owner', (string) $other->id)
        ->set('tab', 'today')
        ->assertDontSee('Someone else')
        ->assertSee('Today meeting');
});

test('the calendar shows the scheduled activities of the week', function () {
    Livewire::actingAs($this->me)->test(Index::class)
        ->set('view', 'calendar')
        ->assertViewHas('events', fn (array $events) => collect($events)->pluck('title')->contains('Today meeting')
            && ! collect($events)->pluck('title')->contains('Next week visit'))
        ->call('nextWeek')
        ->assertViewHas('events', fn (array $events) => collect($events)->pluck('title')->contains('Next week visit'));
});
