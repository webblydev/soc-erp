<?php

namespace App\Modules\Hrm\Livewire;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\OrgTree;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Org chart (docs/09 §4.7): the reporting tree, optionally narrowed to one department.
 */
#[Title('Org chart')]
class OrgChart extends Component
{
    #[Url(except: '')]
    public string $department = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Employee::class);
    }

    public function render(OrgTree $orgTree): View
    {
        return view('livewire.hrm.org-chart', [
            'tree' => $orgTree->build(is_numeric($this->department) ? (int) $this->department : null),
        ]);
    }
}
