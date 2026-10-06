<?php

use App\Models\User;
use App\Modules\Foundation\Actions\AddNote;
use App\Modules\Foundation\Actions\DeleteNote;
use App\Modules\Foundation\Actions\SetNotePinned;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->parent = User::factory()->create();
    $this->actor = userWithPermissions('admin.users.view', 'notes.create', 'notes.delete_own');
});

test('a note is added to the parent by its author', function () {
    $note = app(AddNote::class)->handle($this->parent, ['body' => '  Called about the ID copy.  '], $this->actor);

    expect($note->notable->is($this->parent))->toBeTrue()
        ->and($note->body)->toBe('Called about the ID copy.')
        ->and($note->created_by)->toBe($this->actor->id)
        ->and($note->is_pinned)->toBeFalse();
});

test('adding a note needs notes.create and access to the parent', function () {
    expect(fn () => app(AddNote::class)->handle($this->parent, ['body' => 'Hi'], userWithPermissions('admin.users.view')))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AddNote::class)->handle($this->parent, ['body' => 'Hi'], userWithPermissions('notes.create')))->toThrow(AuthorizationException::class);
});

test('a note needs a body of at most 5000 characters', function () {
    expectValidationError(fn () => app(AddNote::class)->handle($this->parent, ['body' => '   '], $this->actor), 'body');
    expectValidationError(fn () => app(AddNote::class)->handle($this->parent, ['body' => str_repeat('a', 5001)], $this->actor), 'body');
});

test('pinned notes are listed first, then newest first', function () {
    $old = app(AddNote::class)->handle($this->parent, ['body' => 'Old'], $this->actor);
    $new = app(AddNote::class)->handle($this->parent, ['body' => 'New'], $this->actor);

    app(SetNotePinned::class)->handle($old, true, $this->actor);

    expect($this->parent->notes()->forDisplay()->pluck('id')->all())->toBe([$old->id, $new->id]);
});

test('authors may delete their own notes; others need delete_any', function () {
    $note = app(AddNote::class)->handle($this->parent, ['body' => 'Mine'], $this->actor);
    $other = userWithPermissions('admin.users.view', 'notes.create', 'notes.delete_own');

    expect(fn () => app(DeleteNote::class)->handle($note, $other))->toThrow(AuthorizationException::class);

    app(DeleteNote::class)->handle($note, $this->actor);
    expect($note->fresh()->trashed())->toBeTrue();

    $second = app(AddNote::class)->handle($this->parent, ['body' => 'Mine too'], $this->actor);
    app(DeleteNote::class)->handle($second, userWithPermissions('admin.users.view', 'notes.delete_any'));
    expect($second->fresh()->trashed())->toBeTrue();
});
