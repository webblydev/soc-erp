<?php

use App\Modules\Foundation\Services\PermissionManifest;

test('manifests flatten into permission rows', function () {
    $manifest = new PermissionManifest([base_path('tests/Fixtures/permissions/sample.php')]);

    expect(collect($manifest->permissions())->pluck('name')->all())
        ->toBe(['crm.leads.view_own', 'crm.leads.view_all', 'crm.leads.create', 'notes.create'])
        ->and($manifest->permissions()[3])->toMatchArray(['module' => 'notes', 'resource' => '', 'action' => 'create']);
});

test('grant patterns expand against permission names', function () {
    $manifest = new PermissionManifest([base_path('tests/Fixtures/permissions/sample.php')]);

    $grants = $manifest->expandGrants(['crm.leads.view_own', 'crm.leads.view_all', 'notes.create']);

    expect($grants['sales_executive'])->toBe(['crm.leads.view_own', 'notes.create'])
        ->and($grants['viewer'])->toBe(['crm.leads.view_all']);
});
