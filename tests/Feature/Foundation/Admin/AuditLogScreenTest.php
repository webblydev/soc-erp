<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\AuditLog as AuditLogScreen;
use App\Modules\Foundation\Models\AuditLog;
use App\Support\AuditTrail\AuditTrail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->subject = User::factory()->create(['username' => 'subject1']);
    AuditTrail::record($this->subject, 'updated', ['phone' => '01711111111'], ['phone' => '01822222222']);
});

test('the audit log needs admin.audit.view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.audit.index'))->assertForbidden();
    $this->actingAs(userWithPermissions('admin.audit.view'))->get(route('admin.audit.index'))->assertOk();
});

test('filters narrow by record type, record id, event and date', function () {
    Livewire::actingAs(userWithPermissions('admin.audit.view'))
        ->test(AuditLogScreen::class)
        ->set('filters.type', 'user')
        ->set('filters.record', (string) $this->subject->id)
        ->set('filters.event', 'updated')
        ->assertSee('user #'.$this->subject->id)
        ->set('filters.from', now()->addDay()->toDateString())
        ->assertDontSee('user #'.$this->subject->id);
});

test('tampered record and date filters are ignored instead of failing', function () {
    Livewire::actingAs(userWithPermissions('admin.audit.view'))
        ->test(AuditLogScreen::class)
        ->set('filters.record', '1 OR 1=1')
        ->set('filters.from', 'not-a-date')
        ->set('filters.to', '2026-13-45')
        ->assertOk()
        ->assertSee('user #'.$this->subject->id);
});

test('an entry shows each changed field old and new (FD-AC-06 view)', function () {
    $entry = AuditLog::query()->where('event', 'updated')->latest('id')->firstOrFail();

    Livewire::actingAs(userWithPermissions('admin.audit.view'))
        ->test(AuditLogScreen::class)
        ->call('show', $entry->id)
        ->assertDispatched('open-sheet-audit-entry')
        ->assertSee('01711111111')
        ->assertSee('01822222222');
});

test('the selected entry cannot be set from the client', function () {
    Livewire::actingAs(userWithPermissions('admin.audit.view'))
        ->test(AuditLogScreen::class)
        ->set('selectedId', 1);
})->throws(CannotUpdateLockedPropertyException::class);

test('exporting needs admin.audit.export', function () {
    Excel::fake();
    Excel::matchByRegex();

    Livewire::actingAs(userWithPermissions('admin.audit.view'))->test(AuditLogScreen::class)->call('export')->assertForbidden();

    Livewire::actingAs(userWithPermissions('admin.audit.view', 'admin.audit.export'))->test(AuditLogScreen::class)->call('export');
    Excel::assertDownloaded('/^audit-log-\d{8}-\d{6}\.xlsx$/');
});
