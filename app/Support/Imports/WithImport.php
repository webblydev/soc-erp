<?php

namespace App\Support\Imports;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload → preview → confirm → result flow for an ImportDefinition (spec C10). The preview is
 * cached per user and file; confirm re-reads the stored file and judges every row again.
 */
trait WithImport
{
    use WithFileUploads;

    public const PREVIEW_PAGE = 100;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    #[Locked]
    public string $step = 'upload';

    #[Locked]
    public ?string $importPath = null;

    #[Locked]
    public ?string $failedPath = null;

    /** @var array{created?: int, updated?: int, failed?: int} */
    #[Locked]
    public array $result = [];

    public bool $errorsOnly = false;

    public int $previewLimit = self::PREVIEW_PAGE;

    abstract protected function importDefinition(): ImportDefinition;

    abstract protected function importPermission(): string;

    /**
     * File name stem, e.g. `work-items`.
     */
    abstract protected function importName(): string;

    public function downloadTemplate(): BinaryFileResponse
    {
        $this->authorize($this->importPermission());

        return Excel::download(new TemplateExport($this->importDefinition()), $this->importName().'-template.xlsx');
    }

    public function updatedFile(): void
    {
        $this->authorize($this->importPermission());

        $this->validate(['file' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv', 'mimes:xlsx,csv,txt']]);

        $this->discardFiles();
        $this->importPath = ImportStorage::store($this->file);
        $this->file = null;

        try {
            $rows = app(ImportProcessor::class)->preview($this->importDefinition(), ImportStorage::DISK, $this->importPath);
        } catch (ValidationException $exception) {
            $this->discardFiles();
            $this->addError('file', (string) collect($exception->errors())->flatten()->first());

            return;
        }

        Cache::put($this->previewKey(), $rows, now()->addDay());

        $this->step = 'preview';
        $this->errorsOnly = false;
        $this->previewLimit = self::PREVIEW_PAGE;
    }

    public function confirm(): void
    {
        $this->authorize($this->importPermission());
        abort_if($this->step !== 'preview' || $this->importPath === null, 404);

        try {
            $outcome = app(ImportProcessor::class)->run($this->importDefinition(), ImportStorage::DISK, $this->importPath);
        } catch (ValidationException $exception) {
            $this->startOver();
            $this->addError('file', (string) collect($exception->errors())->flatten()->first());

            return;
        }

        if ($outcome->failed !== []) {
            $this->failedPath = ImportStorage::failedPathFor($this->importPath);
            Excel::store(new FailedRowsExport(array_keys($this->importDefinition()->columns()), $outcome->failed), $this->failedPath, ImportStorage::DISK);
        }

        Cache::forget($this->previewKey());
        ImportStorage::delete($this->importPath);
        $this->importPath = null;

        $this->result = ['created' => $outcome->created, 'updated' => $outcome->updated, 'failed' => count($outcome->failed)];
        $this->step = 'result';
    }

    public function downloadFailed(): StreamedResponse
    {
        $this->authorize($this->importPermission());
        abort_if($this->failedPath === null, 404);

        return Storage::disk(ImportStorage::DISK)->download($this->failedPath, $this->importName().'-failed-rows.xlsx');
    }

    public function startOver(): void
    {
        $this->authorize($this->importPermission());

        $this->discardFiles();
        $this->reset('file', 'step', 'result', 'errorsOnly', 'previewLimit');
        $this->resetErrorBag();
    }

    public function showMore(): void
    {
        $this->previewLimit += self::PREVIEW_PAGE;
    }

    /**
     * Rows to show for the preview step, filtered and limited, with counts per status.
     *
     * @return array{rows: list<ImportRow>, counts: array<string, int>, shown: int}
     */
    protected function previewData(): array
    {
        $empty = ['rows' => [], 'counts' => ['new' => 0, 'update' => 0, 'error' => 0], 'shown' => 0];

        if ($this->step !== 'preview' || $this->importPath === null) {
            return $empty;
        }

        /** @var list<ImportRow>|null $rows */
        $rows = Cache::get($this->previewKey());

        if ($rows === null) {
            $rows = app(ImportProcessor::class)->preview($this->importDefinition(), ImportStorage::DISK, $this->importPath);
            Cache::put($this->previewKey(), $rows, now()->addDay());
        }

        $counts = $empty['counts'];

        foreach ($rows as $row) {
            $counts[$row->status->value]++;
        }

        $filtered = $this->errorsOnly
            ? array_values(array_filter($rows, fn (ImportRow $row): bool => $row->status === ImportRowStatus::Error))
            : $rows;

        return ['rows' => array_slice($filtered, 0, max(self::PREVIEW_PAGE, $this->previewLimit)), 'counts' => $counts, 'shown' => count($filtered)];
    }

    private function previewKey(): string
    {
        return 'imports.'.Auth::id().'.'.sha1((string) $this->importPath);
    }

    private function discardFiles(): void
    {
        if ($this->importPath !== null) {
            Cache::forget($this->previewKey());
        }

        ImportStorage::delete($this->importPath);
        ImportStorage::delete($this->failedPath);
        $this->importPath = null;
        $this->failedPath = null;
    }
}
