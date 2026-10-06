<?php

namespace App\Modules\Foundation\Livewire\Shared;

use App\Modules\Foundation\Concerns\InteractsWithCollaborativeParent;
use App\Modules\Foundation\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * The shared history panel (docs/01 §5.14): the record's audit timeline, newest first, with
 * each entry's field changes.
 */
class History extends Component
{
    use InteractsWithCollaborativeParent;

    public const PAGE_SIZE = 20;

    public int $limit = self::PAGE_SIZE;

    public function mount(Model $model): void
    {
        $this->rememberParent($model);
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    public function render(): View
    {
        $parent = $this->parent();

        $entries = AuditLog::query()
            ->where('auditable_type', $parent->getMorphClass())
            ->where('auditable_id', $parent->getKey())
            ->with(['user:id,name,username', 'impersonator:id,username'])
            ->latest('id')
            ->limit($this->limit + 1)
            ->get();

        return view('livewire.shared.history', [
            'entries' => $entries->take($this->limit),
            'hasMore' => $entries->count() > $this->limit,
        ]);
    }
}
