<?php

use App\Models\User;
use App\Modules\Foundation\Actions\AddNote;
use App\Modules\Foundation\Actions\UploadAttachment;
use App\Modules\Foundation\Livewire\Shared\Attachments;
use App\Modules\Foundation\Livewire\Shared\Notes;
use App\Modules\Foundation\Models\Attachment;
use App\Modules\Foundation\Models\Note;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
    $this->parent = User::factory()->create();
    $this->actor = userWithPermissions('admin.users.view', 'admin.users.update', 'attachments.upload', 'attachments.delete_own', 'notes.create', 'notes.delete_own');
});

test('the user form shows the attachments and notes panels when editing', function () {
    $this->actingAs($this->actor)
        ->get(route('admin.users.edit', $this->parent))
        ->assertOk()
        ->assertSeeLivewire(Attachments::class)
        ->assertSeeLivewire(Notes::class);
});

test('the panels refuse a user who cannot see the parent', function () {
    Livewire::actingAs(userWithPermissions('attachments.upload'))
        ->test(Attachments::class, ['model' => $this->parent])
        ->assertForbidden();
});

test('a file is uploaded through the panel and listed with a signed link', function () {
    Livewire::actingAs($this->actor)
        ->test(Attachments::class, ['model' => $this->parent])
        ->call('startUpload')
        ->set('upload', UploadedFile::fake()->create('nid.pdf', 50))
        ->set('title', 'National ID')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('National ID')
        ->assertSee('signature=', false);

    expect($this->parent->attachments()->count())->toBe(1);
});

test('upload rule failures show on the upload field', function () {
    Livewire::actingAs($this->actor)
        ->test(Attachments::class, ['model' => $this->parent])
        ->set('upload', UploadedFile::fake()->create('tool.exe', 5))
        ->call('save')
        ->assertHasErrors('upload');
});

test('replacing a file through the panel lists the new version only', function () {
    $first = app(UploadAttachment::class)->handle($this->parent, UploadedFile::fake()->create('plan.pdf', 10), $this->actor, ['title' => 'Plan']);

    Livewire::actingAs($this->actor)
        ->test(Attachments::class, ['model' => $this->parent])
        ->call('startReplace', $first->id)
        ->set('upload', UploadedFile::fake()->create('plan-v2.pdf', 10))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('v2');

    expect(Attachment::query()->where('version', 2)->value('replaces_attachment_id'))->toBe($first->id);
});

test('an attachment of another record cannot be deleted through the panel', function () {
    $other = User::factory()->create();
    $theirs = app(UploadAttachment::class)->handle($other, UploadedFile::fake()->create('x.pdf', 10), $this->actor);

    Livewire::actingAs($this->actor)
        ->test(Attachments::class, ['model' => $this->parent])
        ->call('delete', $theirs->id)
        ->assertNotFound();
});

test('the uploader deletes a file through the panel', function () {
    $attachment = app(UploadAttachment::class)->handle($this->parent, UploadedFile::fake()->create('plan.pdf', 10), $this->actor);

    Livewire::actingAs($this->actor)
        ->test(Attachments::class, ['model' => $this->parent])
        ->call('delete', $attachment->id)
        ->assertDispatched('toast');

    expect($attachment->fresh()->trashed())->toBeTrue();
});

test('notes are added, pinned and deleted through the panel', function () {
    $component = Livewire::actingAs($this->actor)
        ->test(Notes::class, ['model' => $this->parent])
        ->set('body', 'Asked for a new ID copy.')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertSee('Asked for a new ID copy.');

    $note = Note::query()->sole();

    $component->call('togglePin', $note->id);
    expect($note->fresh()->is_pinned)->toBeTrue();

    $component->call('delete', $note->id);
    expect($note->fresh()->trashed())->toBeTrue();
});

test('an empty note shows a validation error', function () {
    Livewire::actingAs($this->actor)
        ->test(Notes::class, ['model' => $this->parent])
        ->call('add')
        ->assertHasErrors('body');
});

test('a note of another record cannot be changed through the panel', function () {
    $theirs = app(AddNote::class)->handle(User::factory()->create(), ['body' => 'Other'], $this->actor);

    Livewire::actingAs($this->actor)
        ->test(Notes::class, ['model' => $this->parent])
        ->call('delete', $theirs->id)
        ->assertNotFound();
});
