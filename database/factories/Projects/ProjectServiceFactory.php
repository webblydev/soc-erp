<?php

namespace Database\Factories\Projects;

use App\Modules\Catalog\Models\Service;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectService;
use App\Modules\Projects\Models\ProjectServiceStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectService>
 */
class ProjectServiceFactory extends Factory
{
    use ResolvesLookups;

    protected $model = ProjectService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'service_id' => Service::factory(),
            'quantity' => 1,
            'rate' => 100000,
            'discount_amount' => 0,
            'amount' => 100000,
            'project_service_status_id' => fn (): int => self::lookupId(ProjectServiceStatus::class, ProjectServiceStatus::NOT_STARTED),
        ];
    }
}
