<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSequenceFormat
{
    private const TOKEN = '/\{([^}]*)\}/';

    private const ALLOWED = '/^(seq:[1-9]|yy|yyyy|bl_prefix|branch)$/';

    /**
     * @throws ValidationException
     */
    public function handle(NumberSequenceFormat $definition, string $format): void
    {
        $format = trim($format);
        preg_match_all(self::TOKEN, $format, $matches);
        $tokens = $matches[1];

        $error = match (true) {
            $format === '' || mb_strlen($format) > 80 => __('The format must be 1 to 80 characters.'),
            ! in_array(true, array_map(fn (string $token): bool => str_starts_with($token, 'seq:'), $tokens), true) => __('The format must contain a {seq:N} token.'),
            array_filter($tokens, fn (string $token): bool => preg_match(self::ALLOWED, $token) !== 1) !== [] => __('Only {seq:N}, {yy}, {yyyy}, {bl_prefix} and {branch} tokens are allowed (N from 1 to 9).'),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['format' => $error]);
        }

        DB::transaction(function () use ($definition, $format): void {
            $definition->update(['format' => $format]);
            NumberSequence::query()->where('document_type', $definition->document_type)->update(['format' => $format]);
        });
    }
}
