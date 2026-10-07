<?php

namespace App\Modules\Projects\Services;

use App\Modules\Projects\Contracts\ProjectCompletionCheck;
use App\Modules\Projects\Models\Project;

/**
 * Registry of completion checks (spec P2). Modules register theirs from their service provider.
 */
final class ProjectCompletionChecks
{
    /** @var list<class-string<ProjectCompletionCheck>> */
    private array $checks = [];

    /**
     * @param  class-string<ProjectCompletionCheck>  $class
     */
    public function register(string $class): void
    {
        if (! in_array($class, $this->checks, true)) {
            $this->checks[] = $class;
        }
    }

    /**
     * @return list<string>
     */
    public function run(Project $project): array
    {
        $problems = [];

        foreach ($this->checks as $class) {
            /** @var ProjectCompletionCheck $check */
            $check = app($class);
            array_push($problems, ...$check->check($project));
        }

        return $problems;
    }
}
