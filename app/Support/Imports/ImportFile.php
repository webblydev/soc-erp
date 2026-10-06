<?php

namespace App\Support\Imports;

use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Reads the first sheet of an uploaded .xlsx / .csv into trimmed text cells keyed by heading.
 */
final class ImportFile
{
    public const MAX_ROWS = 2000;

    /**
     * @param  list<string>  $requiredHeadings
     * @return array<int, array<string, string>> spreadsheet row number => heading => cell
     *
     * @throws ValidationException
     */
    public static function rows(string $disk, string $path, array $requiredHeadings): array
    {
        try {
            $sheets = Excel::toArray(new class implements Import, WithHeadingRow {}, $path, $disk);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('The file could not be read. Upload an .xlsx or .csv file.')]);
        }

        $rows = [];

        foreach ($sheets[0] ?? [] as $index => $cells) {
            $values = [];

            foreach ($cells as $heading => $cell) {
                if (is_string($heading) && $heading !== '') {
                    $values[$heading] = self::text($cell);
                }
            }

            if (implode('', $values) !== '') {
                $rows[$index + 2] = $values;
            }
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('The file has no data rows.')]);
        }

        $missing = array_diff($requiredHeadings, array_keys(reset($rows)));

        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => __('Missing columns: :columns. Download the template for the expected headings.', ['columns' => implode(', ', $missing)])]);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => __('The file has more than :max rows. Split it and import each part.', ['max' => self::MAX_ROWS])]);
        }

        return $rows;
    }

    private static function text(mixed $cell): string
    {
        return match (true) {
            $cell === null => '',
            is_float($cell) => rtrim(rtrim(number_format($cell, 4, '.', ''), '0'), '.'),
            is_bool($cell) => $cell ? '1' : '0',
            is_scalar($cell) => trim((string) $cell),
            default => '',
        };
    }
}
