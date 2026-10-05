<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\ScopedRecord;

beforeEach(function () {
    Schema::create('scoped_records', function (Blueprint $table) {
        $table->id();
        $table->string('label');
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->unsignedBigInteger('assignee_id')->nullable();
    });

    createPermissions('crm.leads.view_own', 'crm.leads.view_team', 'crm.leads.view_all');

    $this->me = User::factory()->create();
    $this->teammate = User::factory()->create();
    $this->stranger = User::factory()->create();

    ScopedRecord::query()->create(['label' => 'mine', 'owner_id' => $this->me->id]);
    ScopedRecord::query()->create(['label' => 'assigned', 'owner_id' => $this->stranger->id, 'assignee_id' => $this->me->id]);
    ScopedRecord::query()->create(['label' => 'team', 'owner_id' => $this->teammate->id]);
    ScopedRecord::query()->create(['label' => 'other', 'owner_id' => $this->stranger->id]);

    ScopedRecord::$teamUserIds = [$this->teammate->id];
});

function visibleLabels(User $user): array
{
    return ScopedRecord::query()->visibleTo($user, 'crm.leads')->orderBy('label')->pluck('label')->all();
}

test('view_all sees everything', function () {
    $this->me->syncDirectPermissions(['crm.leads.view_all']);

    expect(visibleLabels($this->me))->toBe(['assigned', 'mine', 'other', 'team']);
});

test('view_team sees own and team records', function () {
    $this->me->syncDirectPermissions(['crm.leads.view_team']);

    expect(visibleLabels($this->me))->toBe(['assigned', 'mine', 'team']);
});

test('view_own sees records the user owns or is assigned', function () {
    $this->me->syncDirectPermissions(['crm.leads.view_own']);

    expect(visibleLabels($this->me))->toBe(['assigned', 'mine']);
});

test('no view permission sees nothing', function () {
    expect(visibleLabels($this->me))->toBe([]);
});

test('super admin sees everything', function () {
    ensureRole('super_admin');
    $this->me->assignRole('super_admin');

    expect(visibleLabels($this->me))->toHaveCount(4);
});
