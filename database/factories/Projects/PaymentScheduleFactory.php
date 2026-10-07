<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\PaymentSchedule;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ScheduleStatus;
use App\Modules\Projects\Models\ScheduleTrigger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentSchedule>
 */
class PaymentScheduleFactory extends Factory
{
    use ResolvesLookups;

    protected $model = PaymentSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'milestone_name' => fake()->sentence(3),
            'schedule_trigger_id' => fn (): int => self::lookupId(ScheduleTrigger::class, ScheduleTrigger::MANUAL),
            'amount' => 50000,
            'schedule_status_id' => fn (): int => self::lookupId(ScheduleStatus::class, ScheduleStatus::PENDING),
        ];
    }

    public function triggeredBy(string $code, ?int $refId = null): static
    {
        return $this->state(fn (): array => ['schedule_trigger_id' => self::lookupId(ScheduleTrigger::class, $code), 'trigger_ref_id' => $refId]);
    }
}
