<?php

use App\Modules\Hrm\Livewire\Documents\Expiring;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeDocument;
use App\Modules\Hrm\Models\EmployeeDocumentType;
use Livewire\Livewire;

beforeEach(fn () => seedHrm());

test('documents are bucketed by days to expiry', function () {
    $employee = Employee::factory()->create();
    $type = EmployeeDocumentType::idFor('PASSPORT');
    EmployeeDocument::factory()->for($employee)->expiringOn(today()->subDays(3)->toDateString())->create(['employee_document_type_id' => $type, 'document_no' => 'EXPIRED1']);
    EmployeeDocument::factory()->for($employee)->expiringOn(today()->addDays(20)->toDateString())->create(['employee_document_type_id' => $type, 'document_no' => 'SOON20']);
    EmployeeDocument::factory()->for($employee)->expiringOn(today()->addDays(75)->toDateString())->create(['employee_document_type_id' => $type, 'document_no' => 'LATER75']);

    $component = Livewire::actingAs(userWithPermissions('hrm.documents.manage'))->test(Expiring::class);

    $component->assertSee('SOON20')->assertDontSee('LATER75')->assertDontSee('EXPIRED1');
    $component->set('window', '90')->assertSee('LATER75')->assertSee('SOON20');
    $component->set('window', 'expired')->assertSee('EXPIRED1')->assertDontSee('SOON20');
});

test('the expiry list needs documents.manage', function () {
    $this->actingAs(userWithPermissions('hrm.employees.view_basic'))->get(route('hrm.documents.expiring'))->assertForbidden();
    $this->actingAs(userWithPermissions('hrm.documents.manage'))->get(route('hrm.documents.expiring'))->assertOk();
});

test('bulk delete removes the selected documents', function () {
    $document = EmployeeDocument::factory()->for(Employee::factory())->expiringOn(today()->addDays(5)->toDateString())->create();

    Livewire::actingAs(userWithPermissions('hrm.documents.manage'))->test(Expiring::class)
        ->set('selected', [(string) $document->id])
        ->call('deleteSelected')
        ->assertDispatched('toast', type: 'success', description: '1 record deleted.');

    expect(EmployeeDocument::query()->find($document->id))->toBeNull();
});
