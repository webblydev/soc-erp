<?php

namespace App\Modules\Catalog\Concerns;

use Illuminate\Support\Str;

/**
 * Input clean-up shared by the catalog Save Actions and imports: trimmed strings, upper-case
 * codes (CT-BR-05, spec C4), blank optional fields as null and grouping commas out of rates.
 */
trait NormalisesCatalogInput
{
    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $nullable
     * @return array<string, mixed>
     */
    protected function normalise(array $input, array $nullable): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value);
            }
        }

        if (is_string($input['code'] ?? null)) {
            $input['code'] = Str::upper($input['code']);
        }

        foreach ($input as $key => $value) {
            if (str_ends_with($key, '_rate') && is_string($value)) {
                $input[$key] = str_replace(',', '', $value);
            }
        }

        foreach ($nullable as $key) {
            if (($input[$key] ?? null) === '') {
                $input[$key] = null;
            }
        }

        return $input;
    }
}
