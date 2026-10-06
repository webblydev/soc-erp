<?php

use App\Modules\Foundation\Actions\SaveLookup;
use App\Modules\Foundation\Livewire\Admin\MasterData;
use App\Modules\Foundation\Models\DocumentType;
use Database\Seeders\Foundation\DocumentTypeSeeder;
use Livewire\Livewire;

test('the seeder creates the document types as system rows', function () {
    $this->seed(DocumentTypeSeeder::class);

    expect(DocumentType::query()->count())->toBe(12)
        ->and(DocumentType::query()->where('is_system', false)->exists())->toBeFalse()
        ->and(DocumentType::query()->where('code', 'site_photo')->value('name'))->toBe('Site Photo');
});

test('allowed extensions are saved from comma separated text as a lowercase list', function () {
    $type = app(SaveLookup::class)->handle('document_types', [
        'code' => 'plan', 'name' => 'Plan', 'is_active' => true,
        'allowed_mimes' => ' PDF, dwg ,,png', 'max_size_mb' => 30,
    ]);

    expect($type->refresh()->allowed_mimes)->toBe(['pdf', 'dwg', 'png'])
        ->and($type->max_size_mb)->toBe(30);
});

test('allowed extensions must be from the global list (FD-BR-08)', function () {
    expectValidationError(fn () => app(SaveLookup::class)->handle('document_types', [
        'code' => 'plan', 'name' => 'Plan', 'is_active' => true, 'allowed_mimes' => 'pdf, exe',
    ]), 'allowed_mimes');
});

test('the master data sheet shows the allowed extensions as text', function () {
    $type = DocumentType::factory()->create(['allowed_mimes' => ['pdf', 'jpg']]);

    Livewire::actingAs(superAdmin())
        ->test(MasterData::class, ['table' => 'document_types'])
        ->call('edit', $type->id)
        ->assertSet('form.allowed_mimes', 'pdf, jpg');
});
