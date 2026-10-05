<?php

use App\Models\User;
use App\Modules\Foundation\Models\AuditLog;
use App\Support\AuditTrail\AuditTrail;

function auditEntries(User $user, string $event)
{
    return AuditLog::query()
        ->where('auditable_type', 'user')
        ->where('auditable_id', $user->id)
        ->where('event', $event)
        ->get();
}

test('creating an auditable model writes a created entry without hidden fields', function () {
    $user = User::factory()->create(['name' => 'Rahim']);

    $entry = auditEntries($user, 'created')->sole();

    expect($entry->new_values)->toHaveKey('name', 'Rahim')
        ->and($entry->new_values)->not->toHaveKeys(['password', 'remember_token', 'created_at'])
        ->and($entry->old_values)->toBeNull();
});

test('updating writes only the changed attributes with old and new values', function () {
    $user = User::factory()->create(['phone' => '+8801811000000']);

    $user->update(['phone' => '+8801711000000']);

    $entry = auditEntries($user, 'updated')->sole();

    expect($entry->old_values)->toBe(['phone' => '+8801811000000'])
        ->and($entry->new_values)->toBe(['phone' => '+8801711000000']);
});

test('saving with only excluded attributes changed writes no updated entry', function () {
    $user = User::factory()->create();

    $user->update(['password' => 'another-password', 'last_login_ip' => '10.0.0.1']);

    expect(auditEntries($user, 'updated'))->toBeEmpty();
});

test('deleting and restoring write entries', function () {
    $user = User::factory()->create();

    $user->delete();
    $user->restore();

    expect(auditEntries($user, 'deleted'))->toHaveCount(1)
        ->and(auditEntries($user, 'restored'))->toHaveCount(1);
});

test('custom events record the acting user', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($admin);

    $entry = AuditTrail::record($user, 'approved', null, ['note' => 'ok']);

    expect($entry->user_id)->toBe($admin->id)
        ->and($entry->event)->toBe('approved')
        ->and($entry->auditable_type)->toBe('user')
        ->and($entry->new_values)->toBe(['note' => 'ok']);
});

test('an explicit actor overrides the authenticated user', function () {
    $user = User::factory()->create();

    $entry = AuditTrail::record($user, 'login', actor: $user);

    expect($entry->user_id)->toBe($user->id);
});

test('created_by and updated_by are stamped from the authenticated user', function () {
    $admin = User::factory()->create();
    $editor = User::factory()->create();

    $this->actingAs($admin);
    $user = User::factory()->create();

    $this->actingAs($editor);
    $user->update(['name' => 'Changed']);

    expect($user->refresh()->created_by)->toBe($admin->id)
        ->and($user->updated_by)->toBe($editor->id);
});
