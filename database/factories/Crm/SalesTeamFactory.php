<?php

namespace Database\Factories\Crm;

use App\Models\User;
use App\Modules\Crm\Models\SalesTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesTeam>
 */
class SalesTeamFactory extends Factory
{
    protected $model = SalesTeam::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Team '.fake()->unique()->bothify('??##'),
            'is_active' => true,
        ];
    }

    public function managedBy(User $user): static
    {
        return $this->state(fn (): array => ['manager_user_id' => $user->id]);
    }

    public function withMembers(User ...$users): static
    {
        return $this->afterCreating(function (SalesTeam $team) use ($users): void {
            foreach ($users as $user) {
                $team->members()->create(['user_id' => $user->id, 'joined_on' => today()]);
            }
        });
    }
}
