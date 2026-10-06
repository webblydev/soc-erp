<?php

use App\Modules\Catalog\Imports\WorkItemImport;
use App\Support\Imports\FailedRowsExport;
use App\Support\Imports\ImportFile;
use App\Support\Imports\ImportRow;
use App\Support\Imports\ImportRowStatus;
use App\Support\Imports\ImportStorage;
use App\Support\Imports\TemplateExport;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('private'));

test('missing headings, empty files, too many rows and unreadable files are file errors', function () {
    $disk = Storage::disk('private');
    $required = ['code', 'name', 'unit'];

    $disk->put('imports/a.csv', "code,name\nA,B");
    expectValidationError(fn () => ImportFile::rows('private', 'imports/a.csv', $required), 'file');

    $disk->put('imports/b.csv', 'code,name,unit');
    expectValidationError(fn () => ImportFile::rows('private', 'imports/b.csv', $required), 'file');

    $disk->put('imports/c.csv', "code,name,unit\n".str_repeat("A,B,C\n", ImportFile::MAX_ROWS + 1));
    expectValidationError(fn () => ImportFile::rows('private', 'imports/c.csv', $required), 'file');

    $disk->put('imports/d.xlsx', 'not a spreadsheet');
    expectValidationError(fn () => ImportFile::rows('private', 'imports/d.xlsx', $required), 'file');
});

test('rows are keyed by sheet row number, trimmed, and blank rows skipped', function () {
    Storage::disk('private')->put('imports/e.csv', "Code,Name,Unit\n A ,B,C\n,,\nD,E,F");

    expect(ImportFile::rows('private', 'imports/e.csv', ['code', 'name', 'unit']))->toBe([
        2 => ['code' => 'A', 'name' => 'B', 'unit' => 'C'],
        4 => ['code' => 'D', 'name' => 'E', 'unit' => 'F'],
    ]);
});

test('the template has the definition headings and an example row', function () {
    $template = new TemplateExport(app(WorkItemImport::class));

    expect($template->headings())->toBe(['code', 'name', 'category', 'unit', 'formula', 'rate'])
        ->and($template->array()[0][0])->toBe('EW-001');
});

test('failed rows keep their values, add their errors and neutralise formulas', function () {
    $export = new FailedRowsExport(['code', 'name'], [
        new ImportRow(5, ['code' => '=HYPERLINK("x")', 'name' => 'Item'], ImportRowStatus::Error, ['Bad unit.', 'Bad rate.']),
    ]);

    expect($export->headings())->toBe(['row', 'code', 'name', 'errors'])
        ->and($export->array())->toBe([[5, "'=HYPERLINK(\"x\")", 'Item', 'Bad unit.; Bad rate.']]);
});

test('prune removes import files older than a day', function () {
    $disk = Storage::disk('private');
    $disk->put('imports/old.csv', 'x');
    $disk->put('imports/new.csv', 'x');
    touch($disk->path('imports/old.csv'), now()->subHours(25)->getTimestamp());

    expect(ImportStorage::prune())->toBe(1)
        ->and($disk->exists('imports/old.csv'))->toBeFalse()
        ->and($disk->exists('imports/new.csv'))->toBeTrue();
});
