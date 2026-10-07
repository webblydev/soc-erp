<?php

use App\Modules\Projects\Livewire\Templates\Form;
use App\Modules\Projects\Livewire\Templates\Index;
use App\Modules\Projects\Models\TaskTemplate;
use App\Modules\Projects\Models\TaskType;
use Livewire\Livewire;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
});

test('templates need the manage permission', function () {
    $this->actingAs(staffUser('engineer'))->get(route('projects.templates.index'))->assertForbidden();
    $this->actingAs(staffUser('project_manager'))->get(route('projects.templates.index'))->assertOk()->assertSee('Building Design &amp; RAJUK Approval', false);
});

test('the form loads a template with dependencies and previews dates', function () {
    $template = TaskTemplate::query()->firstOrFail();

    Livewire::actingAs(staffUser('project_manager'))->test(Form::class, ['template' => $template])
        ->assertCount('items', 12)
        ->assertSet('items.8.depends_on', '7')
        ->set('previewStart', '2026-11-01')
        ->assertSee('01 Nov → 04 Nov');
});

test('a new template is saved from the form', function () {
    Livewire::actingAs(staffUser('project_manager'))->test(Form::class)
        ->set('name', 'Interior fit-out')
        ->set('items.0.title', 'Measure site')
        ->set('items.0.task_type_id', TaskType::idFor('SURVEY'))
        ->set('items.0.checklist', "Walls\nCeiling")
        ->call('addItem')
        ->set('items.1.title', 'Concept')
        ->set('items.1.depends_on', '0')
        ->call('save')
        ->assertHasNoErrors();

    $template = TaskTemplate::query()->where('name', 'Interior fit-out')->firstOrFail();
    expect($template->items()->count())->toBe(2)->and($template->items()->first()->checklist)->toBe(['Walls', 'Ceiling']);
});

test('the list deletes a template', function () {
    $template = TaskTemplate::factory()->create(['name' => 'Old template']);

    Livewire::actingAs(staffUser('project_manager'))->test(Index::class)->call('deleteRecord', $template->id);

    expect($template->fresh()->trashed())->toBeTrue();
});
