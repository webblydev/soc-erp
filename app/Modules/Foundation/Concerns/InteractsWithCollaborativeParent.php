<?php

namespace App\Modules\Foundation\Concerns;

use App\Models\User;
use App\Support\Collaboration\Collaborative;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Locked;

/**
 * Keeps the record a shared panel belongs to as a locked morph alias and id, and resolves it
 * again on every request with the viewer's access re-checked.
 */
trait InteractsWithCollaborativeParent
{
    #[Locked]
    public string $parentType = '';

    #[Locked]
    public int $parentId = 0;

    protected function rememberParent(Model $model): void
    {
        if (! $model instanceof Collaborative) {
            throw new InvalidArgumentException($model::class.' does not implement '.Collaborative::class.'.');
        }

        $this->parentType = $model->getMorphClass();
        $this->parentId = (int) $model->getKey();

        $this->parent();
    }

    protected function parent(): Model&Collaborative
    {
        /** @var class-string<Model>|null $class */
        $class = Relation::getMorphedModel($this->parentType);
        abort_if($class === null, 404);

        $parent = $class::query()->findOrFail($this->parentId);
        abort_unless($parent instanceof Collaborative && $parent->isViewableBy($this->actor()), 403);

        return $parent;
    }

    protected function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
