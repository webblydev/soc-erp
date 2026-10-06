<?php

namespace App\Support\Imports;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uploaded import files and failed-row downloads on the private disk, removed after confirm or
 * by the daily prune.
 */
final class ImportStorage
{
    public const DISK = 'private';

    public const DIRECTORY = 'imports';

    public static function store(UploadedFile $file): string
    {
        $name = Str::uuid().'.'.Str::lower($file->getClientOriginalExtension());

        return (string) $file->storeAs(self::DIRECTORY, $name, self::DISK);
    }

    public static function failedPathFor(string $path): string
    {
        return self::DIRECTORY.'/'.pathinfo($path, PATHINFO_FILENAME).'-failed.xlsx';
    }

    public static function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function prune(int $hours = 24): int
    {
        $disk = Storage::disk(self::DISK);
        $cutoff = now()->subHours($hours)->getTimestamp();
        $deleted = 0;

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
