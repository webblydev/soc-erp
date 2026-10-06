<?php

namespace App\Support;

use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Issues document numbers (docs/01 §3.8). Must run inside the caller's DB transaction so
 * the row lock is held until the document is saved (CM-BR-04, FD-AC-05).
 */
class NumberSequenceService
{
    /**
     * @param  array{date?: CarbonInterface|string, bl_prefix?: string, branch?: string}  $context
     */
    public function next(string $documentType, array $context = []): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('NumberSequenceService::next() must be called inside a database transaction.');
        }

        $definition = NumberSequenceFormat::query()->where('document_type', $documentType)->first()
            ?? throw new InvalidArgumentException("No number format is defined for [{$documentType}].");

        $tokens = $this->tokens($context);

        $sequence = $this->lockSequence($definition, $this->scopeKey($definition, $tokens));
        $number = $this->render($sequence->format, $sequence->next_number, $tokens);

        // A query-builder increment, so issuing numbers fires no model events and writes no audit rows.
        NumberSequence::query()->whereKey($sequence->id)->increment('next_number');

        return $number;
    }

    /**
     * Render a format for display (e.g. the admin screen) without issuing a number.
     *
     * @param  array{date?: CarbonInterface|string, bl_prefix?: string, branch?: string}  $context
     */
    public function preview(string $format, int $number, array $context = []): string
    {
        return $this->render($format, $number, $this->tokens($context));
    }

    /**
     * @param  array{date?: CarbonInterface|string, bl_prefix?: string, branch?: string}  $context
     * @return array{yy: string, yyyy: string, bl_prefix: string, branch: string}
     */
    private function tokens(array $context): array
    {
        $fiscalYear = FiscalYear::for(
            CarbonImmutable::parse($context['date'] ?? now()),
            CompanyProfile::fiscalYearStartMonth(),
        );

        return [
            'yy' => $fiscalYear->shortCode(),
            'yyyy' => $fiscalYear->longCode(),
            'bl_prefix' => $context['bl_prefix'] ?? '',
            'branch' => $context['branch'] ?? '',
        ];
    }

    /**
     * @param  array{yy: string, yyyy: string, bl_prefix: string, branch: string}  $tokens
     */
    private function scopeKey(NumberSequenceFormat $definition, array $tokens): string
    {
        $parts = [];

        if ($definition->reset_policy === 'fiscal_year') {
            $parts[] = 'fy:'.$tokens['yy'];
        }

        if ($definition->scope_by === 'business_line') {
            $parts[] = 'bl:'.($tokens['bl_prefix'] !== '' ? $tokens['bl_prefix'] : throw new InvalidArgumentException("[{$definition->document_type}] numbers need a bl_prefix."));
        }

        if ($definition->scope_by === 'branch') {
            $parts[] = 'br:'.($tokens['branch'] !== '' ? $tokens['branch'] : throw new InvalidArgumentException("[{$definition->document_type}] numbers need a branch."));
        }

        return implode('|', $parts);
    }

    /**
     * Lock the scope row, creating it first if needed. The non-locking existence check followed by
     * insertOrIgnore avoids the gap-lock deadlock that SELECT … FOR UPDATE on a missing row causes in MySQL.
     */
    private function lockSequence(NumberSequenceFormat $definition, string $scopeKey): NumberSequence
    {
        $query = fn () => NumberSequence::query()
            ->where('document_type', $definition->document_type)
            ->where('scope_key', $scopeKey);

        if (! $query()->exists()) {
            NumberSequence::query()->insertOrIgnore([
                'document_type' => $definition->document_type,
                'scope_key' => $scopeKey,
                'format' => $definition->format,
                'next_number' => 1,
                'reset_policy' => $definition->reset_policy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $query()->lockForUpdate()->firstOrFail();
    }

    /**
     * The {seq:N} pad width is clamped to 1–9, so a tampered format cannot exhaust memory.
     *
     * @param  array{yy: string, yyyy: string, bl_prefix: string, branch: string}  $tokens
     */
    private function render(string $format, int $sequence, array $tokens): string
    {
        return (string) preg_replace_callback(
            '/\{(seq:(\d+)|yyyy|yy|bl_prefix|branch)\}/',
            fn (array $match): string => str_starts_with($match[1], 'seq:')
                ? str_pad((string) $sequence, max(1, min(9, (int) ($match[2] ?? 1))), '0', STR_PAD_LEFT)
                : $tokens[$match[1]],
            $format,
        );
    }
}
