<?php

namespace Database\Factories\Projects;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use App\Modules\Projects\Models\ProjectRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectEmployee>
 */
class ProjectEmployeeFactory extends Factory
{
    use ResolvesLookups;

    protected $model = ProjectEmployee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'employee_id' => Employee::factory(),
            'project_role_id' => fn (): int => self::lookupId(ProjectRole::class, 'SITE_ENGINEER'),
            'assigned_on' => today()->subWeek()->toDateString(),
            'is_active' => true,
        ];
    }

    public function withRole(string $code): static
    {
        return $this->state(fn (): array => ['project_role_id' => self::lookupId(ProjectRole::class, $code)]);
    }
}
