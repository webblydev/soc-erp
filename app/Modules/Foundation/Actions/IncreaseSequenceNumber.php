<?php

namespace App\Modules\Foundation\Actions;

use App\Modules\Foundation\Models\NumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FD-BR-07: next_number can only increase. Checked again under the row lock so a document
 * issued in the meantime cannot make the new value a step backwards.
 */
class IncreaseSequenceNumber
{
    /**
     * @throws ValidationException
     */
    public function handle(NumberSequence $sequence, int $nextNumber): void
    {
        DB::transaction(function () use ($sequence, $nextNumber): void {
            $locked = NumberSequence::query()->whereKey($sequence->id)->lockForUpdate()->firstOrFail();

            if ($nextNumber <= $locked->next_number) {
                throw ValidationException::withMessages([
                    'next_number' => __('The next number can only increase (it is :current now).', ['current' => $locked->next_number]),
                ]);
            }

            $locked->update(['next_number' => $nextNumber]);
        });
    }
}
