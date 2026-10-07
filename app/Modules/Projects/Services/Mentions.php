<?php

namespace App\Modules\Projects\Services;

use App\Models\User;
use App\Modules\Projects\Models\Task;
use Illuminate\Support\Collection;

/**
 * @username mentions in task comments (docs/04 §3.9, spec P16).
 */
final class Mentions
{
    /**
     * Active users mentioned in the text who can see the task, other than the author.
     *
     * @return Collection<int, User>
     */
    public function recipients(string $body, Task $task, User $author): Collection
    {
        preg_match_all('/(?<![\w@])@([A-Za-z0-9._-]{2,60})/', $body, $matches);
        $usernames = array_values(array_unique(array_map(fn (string $name): string => rtrim(mb_strtolower($name), '.'), $matches[1])));

        if ($usernames === []) {
            return collect();
        }

        return User::query()->where('is_active', true)->whereIn('username', $usernames)->whereKeyNot($author->id)->get()
            ->filter(fn (User $user): bool => $user->can('view', $task))
            ->values();
    }
}
