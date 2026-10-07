<?php

namespace App\Modules\Projects\Livewire\Templates;

use App\Models\User;
use App\Modules\Projects\Actions\DeleteTaskTemplate;
use App\Modules\Projects\Models\TaskTemplate;
use App\Support\Exports\ListingExport;
use App\Support\Listing\WithBulkActions;
use App\Support\Listing\WithListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Task templates list (docs/04 §5.8).
 */
#[Title('Task templates')]
class Index extends Component
{
    use WithBulkActions, WithListing;

    public function mount(): void
    {
        $this->authorize('projects.task_templates.manage');
    }

    /**
     * @return Builder<TaskTemplate>
     */
    protected function listingQuery(): Builder
    {
        return TaskTemplate::query()->with(['service:id,name', 'projectType:id,name'])->withCount('items')->orderBy('name');
    }

    protected function searchColumns(): array
    {
        return ['task_templates.name'];
    }

    protected function sortColumns(): array
    {
        return ['name' => 'task_templates.name'];
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('projects.task_templates.manage');

        return ListingExport::download('task-templates', $this->exportQuery(), [
            'Name' => 'name', 'Service' => 'service.name', 'Project type' => 'projectType.name', 'Tasks' => 'items_count', 'Active' => 'is_active',
        ]);
    }

    protected function authorizeBulkDelete(): void
    {
        $this->authorize('projects.task_templates.manage');
    }

    protected function deleteRow(Model $row): void
    {
        /** @var TaskTemplate $row */
        app(DeleteTaskTemplate::class)->handle($this->actor(), $row);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.projects.templates.index', [
            'rows' => $this->paginatedRows(),
            'mobileRows' => $this->mobileRows(),
        ]);
    }
}
