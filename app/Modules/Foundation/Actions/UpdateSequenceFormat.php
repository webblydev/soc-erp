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

        $seqCount = count(array_filter($tokens, fn (string $token): bool => str_starts_with($token, 'seq:')));

        $error = match (true) {
            $format === '' || mb_strlen($format) > 80 => __('The format must be 1 to 80 characters.'),
            $seqCount !== 1 => __('The format must contain exactly one {seq:N} token.'),
            array_filter($tokens, fn (string $token): bool => preg_match(self::ALLOWED, $token) !== 1) !== [] => __('Only {seq:N}, {yy}, {yyyy}, {bl_prefix} and {branch} tokens are allowed (N from 1 to 9).'),
            $definition->reset_policy === 'fiscal_year' && ! array_intersect(['yy', 'yyyy'], $tokens) => __('This number resets every fiscal year, so the format needs a {yy} or {yyyy} token.'),
            $definition->scope_by === 'business_line' && ! in_array('bl_prefix', $tokens, true) => __('This number is separate per business line, so the format needs a {bl_prefix} token.'),
            $definition->scope_by === 'branch' && ! in_array('branch', $tokens, true) => __('This number is separate per branch, so the format needs a {branch} token.'),
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
