<?php

namespace Database\Factories\Crm;

use App\Models\User;
use App\Modules\Crm\Models\ActivityType;
use App\Modules\Crm\Models\CrmActivity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CrmActivity>
 */
class CrmActivityFactory extends Factory
{
    protected $model = CrmActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_type_id' => ActivityType::factory(),
            'title' => fake()->sentence(3),
            'owner_user_id' => User::factory(),
            'scheduled_at' => now()->addDay(),
        ];
    }

    public function on(Model $subject): static
    {
        return $this->state(fn (): array => ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey()]);
    }

    public function scheduledAt(Carbon $at): static
    {
        return $this->state(fn (): array => ['scheduled_at' => $at]);
    }

    public function done(): static
    {
        return $this->state(fn (): array => ['completed_at' => now(), 'scheduled_at' => null]);
    }
}
