<?php

use App\Modules\Catalog\Livewire\Materials\Import as MaterialsImport;
use App\Modules\Catalog\Livewire\WorkItems\Import;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Catalog\Models\WorkItemCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
    WorkItemCategory::factory()->create(['code' => 'CONCRETE']);
    Unit::factory()->create(['code' => 'cum']);
    $this->importer = userWithPermissions('catalog.work_items.view', 'catalog.work_items.import');
});

function workItemsCsv(string ...$lines): UploadedFile
{
    return UploadedFile::fake()->createWithContent('items.csv', implode("\n", ['code,name,category,unit,formula,rate', ...$lines]));
}

test('the import screens need the import permission', function () {
    $this->actingAs(userWithPermissions('catalog.work_items.view', 'catalog.work_items.create'));
    $this->get(route('catalog.work-items.import'))->assertForbidden();

    $this->actingAs($this->importer);
    $this->get(route('catalog.work-items.import'))->assertOk()->assertSee('Download template');
    $this->get(route('catalog.materials.import'))->assertForbidden();

    $this->actingAs(userWithPermissions('catalog.materials.view', 'catalog.materials.import'));
    $this->get(route('catalog.materials.import'))->assertOk();
});

test('the template downloads as xlsx', function () {
    Livewire::actingAs($this->importer)->test(Import::class)
        ->call('downloadTemplate')
        ->assertFileDownloaded('work-items-template.xlsx');
});

test('upload shows a preview and confirm imports the valid rows', function () {
    $component = Livewire::actingAs($this->importer)->test(Import::class)
        ->set('file', workItemsCsv('RCC-01,RCC in slab,CONCRETE,cum,nos,100', 'RCC-02,Bad unit,CONCRETE,furlong,nos,1'))
        ->assertHasNoErrors()
        ->assertSet('step', 'preview')
        ->assertSee('RCC in slab')
        ->assertSee('Unknown or inactive unit');

    $uploaded = $component->get('importPath');
    expect(Storage::disk('private')->exists($uploaded))->toBeTrue();

    $component->set('errorsOnly', true)->assertDontSee('RCC in slab')->assertSee('Bad unit');

    $component->call('confirm')
        ->assertSet('step', 'result')
        ->assertSet('result', ['created' => 1, 'updated' => 0, 'failed' => 1])
        ->call('downloadFailed')
        ->assertFileDownloaded('work-items-failed-rows.xlsx');

    expect(WorkItem::query()->where('code', 'RCC-01')->exists())->toBeTrue()
        ->and(Storage::disk('private')->exists($uploaded))->toBeFalse();
});

test('a file with missing headings is refused at upload', function () {
    Livewire::actingAs($this->importer)->test(Import::class)
        ->set('file', UploadedFile::fake()->createWithContent('items.csv', "code,name\nA,B"))
        ->assertHasErrors(['file'])
        ->assertSet('step', 'upload')
        ->assertSet('importPath', null);

    expect(Storage::disk('private')->files('imports'))->toBe([]);
});

test('only xlsx and csv files are accepted', function () {
    Livewire::actingAs($this->importer)->test(Import::class)
        ->set('file', UploadedFile::fake()->create('items.pdf', 10, 'application/pdf'))
        ->assertHasErrors(['file']);
});

test('the step and stored path cannot be set from the browser', function () {
    Livewire::actingAs($this->importer)->test(Import::class)->set('step', 'preview');
})->throws(CannotUpdateLockedPropertyException::class);

test('confirm before a preview does nothing', function () {
    Livewire::actingAs($this->importer)->test(Import::class)
        ->call('confirm')
        ->assertStatus(404);
});

test('start over clears the upload', function () {
    $component = Livewire::actingAs($this->importer)->test(Import::class)
        ->set('file', workItemsCsv('RCC-01,RCC in slab,CONCRETE,cum,nos,100'));

    $component->call('startOver')->assertSet('step', 'upload')->assertSet('importPath', null);

    expect(Storage::disk('private')->files('imports'))->toBe([]);
});

test('the materials import screen uses the materials template', function () {
    Livewire::actingAs(userWithPermissions('catalog.materials.view', 'catalog.materials.import'))
        ->test(MaterialsImport::class)
        ->call('downloadTemplate')
        ->assertFileDownloaded('materials-template.xlsx');
});
