<?php

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Actions\AssignLead;
use App\Modules\Crm\Actions\CreateLead;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Crm\Notifications\LeadAssigned;
use App\Modules\Crm\Services\RoundRobinAssigner;
use App\Support\Facades\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedCrm();
    seedAccessControl();
    Notification::fake();

    $this->manager = User::factory()->create();
    $this->manager->assignRole('sales_manager');
    $this->rahim = User::factory()->create();
    $this->rahim->assignRole('sales_executive');
    $this->karim = User::factory()->create();
    $this->karim->assignRole('sales_executive');
    $this->stranger = User::factory()->create();
    $this->stranger->assignRole('sales_executive');

    $this->team = SalesTeam::factory()->managedBy($this->manager)->withMembers($this->rahim, $this->karim)->create();
    $this->actor = $this->manager;
    $this->design = Service::factory()->create();
    $this->survey = Service::factory()->create();
});

test('a manager assigns within the team; history, team and notification follow (CRM-BR-06)', function () {
    $lead = Lead::factory()->create(['sales_team_id' => $this->team->id]);

    app(AssignLead::class)->handle($this->manager, $lead, $this->rahim->id, 'Closest to site');

    expect($lead->fresh())->assigned_to->toBe($this->rahim->id)->sales_team_id->toBe($this->team->id)
        ->and($lead->assignmentHistories()->first())->to_user_id->toBe($this->rahim->id)->reason->toBe('Closest to site');

    Notification::assertSentTo($this->rahim, LeadAssigned::class);
});

test('a manager cannot assign outside the team', function () {
    $lead = Lead::factory()->create(['sales_team_id' => $this->team->id]);

    expectValidationError(fn () => app(AssignLead::class)->handle($this->manager, $lead, $this->stranger->id), 'assigned_to');
});

test('management assigns to anyone and self-assignment sends no notification', function () {
    $director = User::factory()->create();
    $director->assignRole('management');
    $lead = Lead::factory()->create();

    app(AssignLead::class)->handle($director, $lead, $this->stranger->id);
    app(AssignLead::class)->handle($director, $lead->fresh(), $director->id);

    Notification::assertSentTo($this->stranger, LeadAssigned::class);
    Notification::assertNotSentTo($director, LeadAssigned::class);
});

test('a sales executive cannot reassign', function () {
    $lead = Lead::factory()->assignedTo($this->rahim)->create();

    app(AssignLead::class)->handle($this->rahim, $lead, $this->karim->id);
})->throws(AuthorizationException::class);

test('closed and converted leads cannot be reassigned', function () {
    $lead = Lead::factory()->withStatus('LOST')->create(['sales_team_id' => $this->team->id]);

    expectValidationError(fn () => app(AssignLead::class)->handle($this->manager, $lead, $this->rahim->id), 'lead');
});

test('round-robin picks the member with fewest open leads, then the longest idle (spec R9)', function () {
    Lead::factory()->assignedTo($this->rahim)->create(['assigned_at' => now()->subDays(3)]);

    expect(app(RoundRobinAssigner::class)->pick($this->team))->toBe($this->karim->id);

    Lead::factory()->assignedTo($this->karim)->create(['assigned_at' => now()->subDay()]);

    expect(app(RoundRobinAssigner::class)->pick($this->team))->toBe($this->rahim->id);
});

test('create uses round-robin when the mode is on and no assignee is given', function () {
    Settings::set('crm.auto_assign_mode', 'round_robin_team');
    $actor = userWithPermissions('crm.leads.view_all', 'crm.leads.create');

    $lead = app(CreateLead::class)->handle($actor, [...leadInput(), 'assigned_to' => null, 'sales_team_id' => $this->team->id]);

    expect($lead->assigned_to)->toBeIn([$this->rahim->id, $this->karim->id])->and($lead->sales_team_id)->toBe($this->team->id);
});
