<?php

namespace App\Support\Imports;

use Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Judges every row of an import file (spec C10). preview() only reports; run() re-reads the file,
 * judges again against the current data and saves the valid rows in one transaction.
 */
final class ImportProcessor
{
    /**
     * @return list<ImportRow>
     *
     * @throws ValidationException
     */
    public function preview(ImportDefinition $definition, string $disk, string $path): array
    {
        $rows = [];

        foreach ($this->evaluate($definition, ImportFile::rows($disk, $path, $definition->requiredHeadings())) as $judged) {
            $rows[] = $judged['row'];
        }

        return $rows;
    }

    /**
     * @throws ValidationException
     */
    public function run(ImportDefinition $definition, string $disk, string $path): ImportOutcome
    {
        $rows = ImportFile::rows($disk, $path, $definition->requiredHeadings());

        return DB::transaction(function () use ($definition, $rows): ImportOutcome {
            $created = 0;
            $updated = 0;
            $failed = [];

            foreach ($this->evaluate($definition, $rows) as ['row' => $row, 'input' => $input, 'existing' => $existing]) {
                if ($row->status === ImportRowStatus::Error) {
                    $failed[] = $row;

                    continue;
                }

                $definition->save($input, $existing);
                $row->status === ImportRowStatus::New ? $created++ : $updated++;
            }

            return new ImportOutcome($created, $updated, $failed);
        });
    }

    /**
     * Rows are judged lazily so run() validates each one after the rows before it were saved.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return Generator<int, array{row: ImportRow, input: array<string, mixed>, existing: Model|null}>
     */
    private function evaluate(ImportDefinition $definition, array $rows): Generator
    {
        $seen = [];

        foreach ($rows as $number => $values) {
            $code = Str::upper(trim($values['code'] ?? ''));
            $existing = $code !== '' ? $definition->findExisting($code) : null;
            ['input' => $input, 'errors' => $errors] = $definition->toInput($values, $existing);

            $validator = $definition->validator($input, $existing);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors[$field] ??= $messages[0];
                }
            }

            if ($code !== '' && isset($seen[$code])) {
                $errors['code'] = __('Code :code is also on row :row.', ['code' => $code, 'row' => $seen[$code]]);
            }

            if ($code !== '') {
                $seen[$code] ??= $number;
            }

            $status = match (true) {
                $errors !== [] => ImportRowStatus::Error,
                $existing !== null => ImportRowStatus::Update,
                default => ImportRowStatus::New,
            };

            yield [
                'row' => new ImportRow($number, $values, $status, array_values($errors)),
                'input' => $input,
                'existing' => $existing,
            ];
        }
    }
}
