<?php

namespace App\Modules\Estimation\Services;

use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Projects\Models\Project;
use App\Support\Facades\Settings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;

/**
 * BOQ quantity limits for the Measurement Book (ES-BR-08, ES-AC-04, spec E13). The cumulative
 * quantity follows the BOQ item across revisions through origin_line_id.
 *
 * @phpstan-type Progress array{boq: string, previous: string, this: string, cumulative: string, pct: string|null, state: 'ok'|'warn'|'block', limit_pct: int}
 */
final class MeasurementLimits
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const BLOCK = 'block';

    /**
     * Work lines of the project's approved estimates that can be measured against: no deductions.
     *
     * @return Builder<EstimateLine>
     */
    public function boqLines(Project $project): Builder
    {
        return EstimateLine::query()
            ->where('deduction', false)
            ->whereIn('estimate_id', Estimate::query()->select('id')
                ->where('project_id', $project->id)
                ->where('estimate_status_id', EstimateStatus::idFor(EstimateStatus::APPROVED)))
            ->orderBy('estimate_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isBoqLine(Project $project, int $lineId): bool
    {
        return $this->boqLines($project)->whereKey($lineId)->exists();
    }

    /**
     * Quantity already measured on the BOQ item (every revision), rejected entries left out.
     */
    public function previous(EstimateLine $line, ?MeasurementEntry $except = null): string
    {
        $origin = $line->originId();

        $quantities = MeasurementEntry::query()
            ->where('mb_status_id', '!=', MbStatus::idFor(MbStatus::REJECTED))
            ->whereIn('estimate_line_id', EstimateLine::query()->select('id')
                ->where(fn (Builder $query) => $query->whereKey($origin)->orWhere('origin_line_id', $origin)))
            ->when($except?->exists, fn (Builder $query) => $query->whereKeyNot($except->id))
            ->pluck('quantity');

        return (string) $quantities->reduce(fn (BigDecimal $sum, mixed $quantity): BigDecimal => $sum->plus((string) $quantity), BigDecimal::zero())->toScale(4);
    }

    /**
     * Where this entry would take the BOQ item: fine up to the BOQ quantity, a warning up to
     * site.mb_allow_exceed_boq_pct above it, blocked beyond.
     *
     * @return Progress
     */
    public function check(EstimateLine $line, string $quantity, ?MeasurementEntry $except = null): array
    {
        $boq = BigDecimal::of($line->quantity)->toScale(4);
        $previous = BigDecimal::of($this->previous($line, $except));
        $cumulative = $previous->plus($quantity)->toScale(4);
        $limitPct = (int) Settings::get('site.mb_allow_exceed_boq_pct', 10);
        $ceiling = $boq->multipliedBy(100 + $limitPct)->dividedBy(100, 4, RoundingMode::HalfUp);

        return [
            'boq' => (string) $boq,
            'previous' => (string) $previous,
            'this' => (string) BigDecimal::of($quantity)->toScale(4),
            'cumulative' => (string) $cumulative,
            'pct' => $boq->isZero() ? null : (string) $cumulative->multipliedBy(100)->dividedBy($boq, 4, RoundingMode::HalfUp),
            'state' => match (true) {
                $cumulative->isLessThanOrEqualTo($boq) => self::OK,
                $cumulative->isLessThanOrEqualTo($ceiling) => self::WARN,
                default => self::BLOCK,
            },
            'limit_pct' => $limitPct,
        ];
    }
}
