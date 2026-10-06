<?php

use App\Modules\Hrm\Actions\DeleteEmployeeDocument;
use App\Modules\Hrm\Actions\SaveEmployeeDocument;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeDocumentType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedHrm();
    Storage::fake(config('foundation.attachments.disk'));
    $this->actor = userWithPermissions('hrm.employees.view_basic', 'hrm.employees.view_full', 'hrm.documents.view', 'hrm.documents.manage', 'attachments.upload');
    $this->employee = Employee::factory()->create();
});

test('a passport needs an expiry date and its file is an attachment on the employee', function () {
    $passport = EmployeeDocumentType::idFor('PASSPORT');

    expectValidationError(fn () => app(SaveEmployeeDocument::class)->handle($this->actor, $this->employee, ['employee_document_type_id' => $passport, 'document_no' => 'A123']), 'expiry_date');

    $document = app(SaveEmployeeDocument::class)->handle($this->actor, $this->employee, ['employee_document_type_id' => $passport, 'document_no' => 'A123', 'expiry_date' => today()->addDays(20)->toDateString()], UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'));

    expect($document->attachment->attachable_id)->toBe($this->employee->id)
        ->and($document->attachment->title)->toBe('Passport')
        ->and($document->expiryState())->toBe('expiring');
});

test('replacing the file keeps the attachment history', function () {
    $document = app(SaveEmployeeDocument::class)->handle($this->actor, $this->employee, ['employee_document_type_id' => EmployeeDocumentType::idFor('CV')], UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'));
    $first = $document->attachment_id;

    app(SaveEmployeeDocument::class)->handle($this->actor, $this->employee, ['employee_document_type_id' => EmployeeDocumentType::idFor('CV')], UploadedFile::fake()->create('cv2.pdf', 50, 'application/pdf'), $document);

    expect($document->fresh()->attachment_id)->not->toBe($first)
        ->and($document->fresh()->attachment->replaces_attachment_id)->toBe($first);
});

test('changing the expiry date clears the expiry notice', function () {
    $document = app(SaveEmployeeDocument::class)->handle($this->actor, $this->employee, ['employee_document_type_id' => EmployeeDocumentType::idFor('PASSPORT'), 'expiry_date' => today()->addDays(10)->toDateString()]);
    $document->forceFill(['expiry_notified_at' => now()])->save();

    app(SaveEmployeeDocument::class)->handle($this->actor, $this->employee, ['employee_document_type_id' => EmployeeDocumentType::idFor('PASSPORT'), 'expiry_date' => today()->addYears(5)->toDateString()], null, $document);

    expect($document->fresh()->expiry_notified_at)->toBeNull();
});

test('a document of another employee cannot be edited through this one', function () {
    $other = Employee::factory()->create();
    $document = app(SaveEmployeeDocument::class)->handle($this->actor, $other, ['employee_document_type_id' => EmployeeDocumentType::idFor('CV')]);

    expect(fn () => app(SaveEmployeeDocument::class)->handle($this->actor, $this->employee, ['employee_document_type_id' => EmployeeDocumentType::idFor('CV')], null, $document))
        ->toThrow(AuthorizationException::class);
});

test('documents need the manage permission and deleting removes the file', function () {
    expect(fn () => app(SaveEmployeeDocument::class)->handle(userWithPermissions('hrm.employees.view_basic'), $this->employee, ['employee_document_type_id' => EmployeeDocumentType::idFor('CV')]))
        ->toThrow(AuthorizationException::class);

    $document = app(SaveEmployeeDocument::class)->handle($this->actor, $this->employee, ['employee_document_type_id' => EmployeeDocumentType::idFor('CV')], UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'));
    app(DeleteEmployeeDocument::class)->handle($this->actor, $document);

    expect($this->employee->documents()->count())->toBe(0)->and($this->employee->attachments()->count())->toBe(0);
});

test('a basic viewer cannot download an employee document file', function () {
    $document = app(SaveEmployeeDocument::class)->handle($this->actor, $this->employee, ['employee_document_type_id' => EmployeeDocumentType::idFor('NID')], UploadedFile::fake()->create('nid.pdf', 20, 'application/pdf'));
    $url = $document->attachment->downloadUrl();

    $this->actingAs(userWithPermissions('hrm.employees.view_basic'))->get($url)->assertForbidden();
    $this->actingAs($this->actor)->get($url)->assertOk();
});

test('deleting a document soft-deletes it and its file', function () {
    $document = app(SaveEmployeeDocument::class)->handle($this->actor, $this->employee, ['employee_document_type_id' => EmployeeDocumentType::idFor('CV')], UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'));
    $attachment = $document->attachment;

    app(DeleteEmployeeDocument::class)->handle($this->actor, $document);

    $this->assertSoftDeleted($document);
    $this->assertSoftDeleted($attachment);
});
