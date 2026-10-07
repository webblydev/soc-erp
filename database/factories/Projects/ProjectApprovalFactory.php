<?php

namespace Database\Factories\Projects;

use App\Modules\Projects\Models\ApprovalAuthority;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\ApprovalType;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectApproval>
 */
class ProjectApprovalFactory extends Factory
{
    use ResolvesLookups;

    protected $model = ProjectApproval::class;

    /**
     * is_final / is_success per seeded status.
     */
    private const STATUS_FLAGS = [
        ApprovalStatus::APPROVED => ['is_final' => true, 'is_success' => true],
        ApprovalStatus::REJECTED => ['is_final' => true],
        ApprovalStatus::WITHDRAWN => ['is_final' => true],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'approval_authority_id' => fn (): int => self::lookupId(ApprovalAuthority::class, ApprovalAuthority::RAJUK),
            'approval_type_id' => fn (): int => self::lookupId(ApprovalType::class, ApprovalType::BP, ['typical_days' => 90]),
            'approval_status_id' => fn (): int => self::statusId(ApprovalStatus::PREPARING),
        ];
    }

    public function withStatus(string $code): static
    {
        return $this->state(fn (): array => ['approval_status_id' => self::statusId($code)]);
    }

    private static function statusId(string $code): int
    {
        return self::lookupId(ApprovalStatus::class, $code, self::STATUS_FLAGS[$code] ?? []);
    }
}
