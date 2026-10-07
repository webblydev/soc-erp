<?php

namespace App\Modules\Estimation\Services;

use App\Modules\Estimation\Models\Estimate;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Line-by-line difference of two revisions of one estimate (docs/05 §5.3, ES-AC-03, spec E19).
 * Lines pair by their origin (the BOQ item they were copied from), else by line number.
 *
 * @phpstan-type Row array{state: 'added'|'removed'|'changed'|'same', line_no: string|null, description: string, unit: string|null, from: array{quantity: string, rate: string|null, amount: string}|null, to: array{quantity: string, rate: string|null, amount: string}|null, quantity_change: string, amount_change: string}
 */
final class EstimateComparison
{
    public const ADDED = 'added';

    public const REMOVED = 'removed';

    public const CHANGED = 'changed';

    public const SAME = 'same';

    /**
     * @return array{from: Estimate, to: Estimate, lines: list<Row>, materials: list<Row>, totals: array{subtotal: string, total: string}}
     */
    public function between(Estimate $first, Estimate $second): array
    {
        [$from, $to] = $first->revision_no <= $second->revision_no ? [$first, $second] : [$second, $first];

        foreach ([$from, $to] as $estimate) {
            $estimate->loadMissing(['lines.unit:id,symbol', 'materialLines.unit:id,symbol', 'materialLines.material:id,name']);
        }

        return [
            'from' => $from,
            'to' => $to,
            'lines' => $this->pair($from->lines, $to->lines, fn ($line): string => $line->description, 'quantity'),
            'materials' => $this->pair($from->materialLines, $to->materialLines, fn ($line): string => $line->displayName(), 'total_qty'),
            'totals' => [
                'subtotal' => (string) BigDecimal::of($to->subtotal)->minus($from->subtotal),
                'total' => (string) BigDecimal::of($to->total_amount)->minus($from->total_amount),
            ],
        ];
    }

    /**
     * @param  Collection<int, covariant Model>  $old
     * @param  Collection<int, covariant Model>  $new
     * @param  callable(Model): string  $label
     * @return list<Row>
     */
    private function pair(Collection $old, Collection $new, callable $label, string $quantityField): array
    {
        $oldByKey = $old->keyBy(fn (Model $line): string => 'o'.$line->originId());
        $rows = [];
        $seen = [];

        foreach ($new as $line) {
            $match = $line->getAttribute('origin_line_id') !== null ? $oldByKey->get('o'.$line->getAttribute('origin_line_id')) : null;
            $match ??= $line->getAttribute('line_no') !== null ? $old->first(fn (Model $candidate): bool => $candidate->getAttribute('line_no') === $line->getAttribute('line_no') && ! in_array($candidate->getKey(), $seen, true)) : null;

            if ($match !== null) {
                $seen[] = $match->getKey();
            }

            $rows[] = $this->row($match, $line, $label, $quantityField);
        }

        foreach ($old->reject(fn (Model $line): bool => in_array($line->getKey(), $seen, true)) as $line) {
            $rows[] = $this->row($line, null, $label, $quantityField);
        }

        return $rows;
    }

    /**
     * @param  callable(Model): string  $label
     * @return Row
     */
    private function row(?Model $from, ?Model $to, callable $label, string $quantityField): array
    {
        $figures = fn (?Model $line): ?array => $line === null ? null : [
            'quantity' => (string) $line->getAttribute($quantityField),
            'rate' => $line->getAttribute('rate') !== null ? (string) $line->getAttribute('rate') : null,
            'amount' => (string) $line->getAttribute('amount'),
        ];
        $a = $figures($from);
        $b = $figures($to);
        $line = $to ?? $from;

        $state = match (true) {
            $a === null => self::ADDED,
            $b === null => self::REMOVED,
            ! BigDecimal::of($a['quantity'])->isEqualTo($b['quantity']) || ! BigDecimal::of($a['amount'])->isEqualTo($b['amount'])
                || ! BigDecimal::of($a['rate'] ?? '0')->isEqualTo($b['rate'] ?? '0') => self::CHANGED,
            default => self::SAME,
        };

        return [
            'state' => $state,
            'line_no' => $line?->getAttribute('line_no'),
            'description' => $label($line),
            'unit' => $line?->getRelation('unit')?->symbol,
            'from' => $a,
            'to' => $b,
            'quantity_change' => (string) BigDecimal::of($b['quantity'] ?? '0')->minus($a['quantity'] ?? '0'),
            'amount_change' => (string) BigDecimal::of($b['amount'] ?? '0')->minus($a['amount'] ?? '0'),
        ];
    }
}
