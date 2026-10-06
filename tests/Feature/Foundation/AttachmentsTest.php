<?php

use App\Models\User;
use App\Modules\Foundation\Actions\DeleteAttachment;
use App\Modules\Foundation\Actions\UploadAttachment;
use App\Modules\Foundation\Models\Attachment;
use App\Modules\Foundation\Models\DocumentType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->parent = User::factory()->create();
    $this->actor = userWithPermissions('admin.users.view', 'attachments.upload', 'attachments.delete_own');
});

function uploadTo(User $parent, User $actor, ?UploadedFile $file = null, array $input = [], ?Attachment $replaces = null): Attachment
{
    return app(UploadAttachment::class)->handle($parent, $file ?? UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'), $actor, $input, $replaces);
}

test('an upload is stored on the private disk and recorded against the parent', function () {
    $attachment = uploadTo($this->parent, $this->actor, input: ['title' => 'Signed plan']);

    expect($attachment->attachable->is($this->parent))->toBeTrue()
        ->and($attachment->disk)->toBe('private')
        ->and($attachment->original_name)->toBe('plan.pdf')
        ->and($attachment->title)->toBe('Signed plan')
        ->and($attachment->version)->toBe(1)
        ->and($attachment->uploaded_by)->toBe($this->actor->id)
        ->and($attachment->path)->toStartWith("attachments/user/{$this->parent->id}/");

    Storage::disk('private')->assertExists($attachment->path);
});

test('uploading needs the upload permission and access to the parent', function () {
    expect(fn () => uploadTo($this->parent, userWithPermissions('admin.users.view')))->toThrow(AuthorizationException::class)
        ->and(fn () => uploadTo($this->parent, userWithPermissions('attachments.upload')))->toThrow(AuthorizationException::class);
});

test('files outside the allowed extensions are refused (FD-BR-08)', function () {
    expectValidationError(fn () => uploadTo($this->parent, $this->actor, UploadedFile::fake()->create('virus.exe', 10)), 'file');
});

test('files over the default 20 MB limit are refused', function () {
    expectValidationError(fn () => uploadTo($this->parent, $this->actor, UploadedFile::fake()->create('big.pdf', 20 * 1024 + 1)), 'file');
});

test('a document type narrows the extensions and sets the size limit', function () {
    $photo = DocumentType::factory()->create(['allowed_mimes' => ['jpg', 'png'], 'max_size_mb' => 1]);

    expectValidationError(fn () => uploadTo($this->parent, $this->actor, input: ['document_type_id' => $photo->id]), 'file');
    expectValidationError(fn () => uploadTo($this->parent, $this->actor, UploadedFile::fake()->create('site.jpg', 1025), ['document_type_id' => $photo->id]), 'file');

    expect(uploadTo($this->parent, $this->actor, UploadedFile::fake()->create('site.jpg', 500), ['document_type_id' => $photo->id])->document_type_id)->toBe($photo->id);
});

test('inactive document types cannot be chosen', function () {
    $type = DocumentType::factory()->create(['is_active' => false]);

    expectValidationError(fn () => uploadTo($this->parent, $this->actor, input: ['document_type_id' => $type->id]), 'document_type_id');
});

test('replacing a file adds the next version and only the latest is listed', function () {
    $type = DocumentType::factory()->create();
    $first = uploadTo($this->parent, $this->actor, input: ['title' => 'Plan', 'document_type_id' => $type->id]);
    $second = uploadTo($this->parent, $this->actor, UploadedFile::fake()->create('plan-v2.pdf', 100), replaces: $first);

    expect($second->version)->toBe(2)
        ->and($second->replaces_attachment_id)->toBe($first->id)
        ->and($second->title)->toBe('Plan')
        ->and($second->document_type_id)->toBe($type->id)
        ->and($this->parent->attachments()->latestVersions()->pluck('id')->all())->toBe([$second->id])
        ->and($second->previousVersions()->pluck('id')->all())->toBe([$first->id]);
});

test('an old version cannot be replaced', function () {
    $first = uploadTo($this->parent, $this->actor);
    uploadTo($this->parent, $this->actor, replaces: $first);

    expectValidationError(fn () => uploadTo($this->parent, $this->actor, replaces: $first->fresh()), 'file');
});

test('the uploader may delete their own file within 24 hours', function () {
    $attachment = uploadTo($this->parent, $this->actor);

    app(DeleteAttachment::class)->handle($attachment, $this->actor);

    expect($attachment->fresh()->trashed())->toBeTrue();
});

test('own files older than 24 hours and other people\'s files need delete_any', function () {
    $old = uploadTo($this->parent, $this->actor);
    $old->forceFill(['created_at' => now()->subHours(25)])->save();
    $theirs = uploadTo($this->parent, userWithPermissions('admin.users.view', 'attachments.upload'));

    expect(fn () => app(DeleteAttachment::class)->handle($old, $this->actor))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteAttachment::class)->handle($theirs, $this->actor))->toThrow(AuthorizationException::class);

    app(DeleteAttachment::class)->handle($theirs, userWithPermissions('admin.users.view', 'attachments.delete_any'));

    expect($theirs->fresh()->trashed())->toBeTrue();
});

test('a signed download serves the file to a user who can see the parent', function () {
    $attachment = uploadTo($this->parent, $this->actor);

    $this->actingAs($this->actor)->get($attachment->downloadUrl())
        ->assertOk()
        ->assertDownload('plan.pdf');
});

test('a copied download URL is refused for a user without access to the parent (FD-AC-07)', function () {
    $attachment = uploadTo($this->parent, $this->actor);

    $this->actingAs(userWithPermissions('attachments.upload'))->get($attachment->downloadUrl())->assertForbidden();
});

test('an unsigned or expired download URL is refused', function () {
    $attachment = uploadTo($this->parent, $this->actor);

    $this->actingAs($this->actor)->get(route('attachments.download', $attachment))->assertForbidden();

    $url = $attachment->downloadUrl();
    $this->travel(31)->minutes();

    $this->actingAs($this->actor)->get($url)->assertForbidden();
});
