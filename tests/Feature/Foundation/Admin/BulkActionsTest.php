<?php

use App\Models\User;
use App\Modules\Foundation\Livewire\Admin\AuditLog;
use App\Support\Exports\QueryExport;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Fixtures\BulkListingFixture;

test('export selected downloads only the selected rows', function () {
    Excel::fake();
    $this->travelTo(now()->setDate(2026, 10, 7)->setTime(10, 0, 0));
    User::factory()->create(['username' => 'first']);
    $second = User::factory()->create(['username' => 'second']);

    Livewire::actingAs(userWithPermissions('admin.users.delete'))->test(BulkListingFixture::class)
        ->set('selected', [(string) $second->id])
        ->call('exportSelected');

    Excel::assertDownloaded('fixture-20261007-100000.xlsx', fn (QueryExport $export): bool => $export->query()->pluck('username')->all() === ['second']);
});

test('delete selected removes rows, skips refused ones with the reason and clears the selection', function () {
    $doomed = User::factory()->create(['username' => 'doomed']);
    $locked = User::factory()->create(['username' => 'locked']);

    Livewire::actingAs(userWithPermissions('admin.users.delete'))->test(BulkListingFixture::class)
        ->set('selected', [(string) $doomed->id, (string) $locked->id])
        ->call('deleteSelected')
        ->assertDispatched('toast', type: 'warning', description: '1 deleted, 1 skipped. Locked rows stay.')
        ->assertSet('selected', []);

    expect($doomed->fresh()->trashed())->toBeTrue()->and($locked->fresh()->trashed())->toBeFalse();
});

test('selected ids outside the current filters are not deleted', function () {
    $inactive = User::factory()->create(['is_active' => false]);

    Livewire::actingAs(userWithPermissions('admin.users.delete'))->test(BulkListingFixture::class)
        ->set('filters.active', '1')
        ->set('selected', [(string) $inactive->id])
        ->call('deleteSelected')
        ->assertDispatched('toast', type: 'error', description: '0 deleted, 1 skipped.');

    expect($inactive->fresh()->trashed())->toBeFalse();
});

test('a new search clears the selection', function () {
    $user = User::factory()->create();

    Livewire::actingAs(userWithPermissions('admin.users.delete'))->test(BulkListingFixture::class)
        ->set('selected', [(string) $user->id])
        ->set('search', 'someone')
        ->assertSet('selected', []);
});

test('deleting needs the screen permission', function () {
    $user = User::factory()->create();

    Livewire::actingAs(userWithPermissions('admin.users.view'))->test(BulkListingFixture::class)
        ->set('selected', [(string) $user->id])
        ->call('deleteSelected')
        ->assertForbidden();

    expect($user->fresh()->trashed())->toBeFalse();
});

test('screens without delete refuse bulk delete', function () {
    Livewire::actingAs(superAdmin())->test(AuditLog::class)
        ->set('selected', ['1'])
        ->call('deleteSelected')
        ->assertForbidden();
});
