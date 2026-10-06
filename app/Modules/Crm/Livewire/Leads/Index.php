<?php

namespace App\Modules\Crm\Livewire\Leads;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Crm\Actions\AssignLead;
use App\Modules\Crm\Actions\ChangeLeadStatus;
use App\Modules\Crm\Concerns\FiltersByLocation;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Models\SalesTeam;
use App\Support\Exports\ListingExport;
use App\Support\Facades\Settings;
use App\Support\Listing\WithListing;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Leads list and Kanban (docs/03 §5.1, spec R5): preset chips, filters, bulk actions and export.
 */
#[Title('Leads')]
class Index extends Component
{
    use FiltersByLocation, WithListing;

    public const PRESETS = ['my_open', 'today', 'overdue', 'unassigned', 'won_month', 'lost_month'];

    public const KANBAN_CARDS_PER_COLUMN = 50;

    #[Url(except: 'list')]
    public string $view = 'list';

    /** @var list<string> */
    #[Url(except: [])]
    public array $statusFilter = [];

    /** @var list<string> */
    public array $selected = [];

    public string $bulkAssigneeId = '';

    public string $bulkStatusId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Lead::class);
    }

    public function applyPreset(string $preset): void
    {
        if (! in_array($preset, self::PRESETS, true)) {
            return;
        }

        $this->statusFilter = [];
        $this->filters = match ($preset) {
            'my_open' => ['state' => 'open', 'assigned' => 'me'],
            'today' => ['state' => 'open', 'follow_up' => 'today'],
            'overdue' => ['state' => 'open', 'follow_up' => 'overdue'],
            'unassigned' => ['state' => 'open', 'assigned' => 'none'],
            'won_month' => ['state' => 'won', 'closed_month' => '1'],
            'lost_month' => ['state' => 'lost', 'closed_month' => '1'],
        };
        $this->updatedFilters();
    }

    public function updatedStatusFilter(): void
    {
        $this->updatedFilters();
    }

    /**
     * @return Builder<Lead>
     */
    protected function listingQuery(): Builder
    {
        return Lead::query()
            ->visibleTo($this->actor(), 'crm.leads')
            ->with(['status:id,name,color,code,is_closed', 'source:id,name', 'priority:id,name,color', 'level:id,name', 'businessLine:id,name', 'assignee:id,name', 'team:id,name', 'services.service:id,name'])
            ->latest('lead_date')
            ->orderByDesc('leads.id');
    }

    protected function searchColumns(): array
    {
        return ['leads.lead_number', 'leads.name', 'leads.company_name', 'leads.phone', 'leads.whatsapp', 'leads.email'];
    }

    protected function sortColumns(): array
    {
        return ['number' => 'leads.lead_number', 'date' => 'leads.lead_date', 'name' => 'leads.name', 'value' => 'leads.expected_value', 'follow_up' => 'leads.next_follow_up_at', 'activity' => 'leads.last_activity_at'];
    }

    /**
     * @param  Builder<Lead>  $query
     */
    protected function applyFilters(Builder $query): void
    {
        match ($this->filterString('state')) {
            'won' => $query->whereIn('leads.lead_status_id', LeadStatus::query()->select('id')->where('is_won', true)),
            'lost' => $query->whereIn('leads.lead_status_id', LeadStatus::query()->select('id')->where('is_lost', true)),
            'all' => null,
            default => $query->open(),
        };

        if ($this->filterString('closed_month') === '1') {
            $column = $this->filterString('state') === 'lost' ? 'leads.lost_at' : 'leads.won_at';
            $query->whereBetween($column, [now()->startOfMonth(), now()->endOfMonth()]);
        }

        $statusIds = $this->statusIds();

        if ($statusIds !== []) {
            $query->whereIn('leads.lead_status_id', $statusIds);
        }

        foreach (['source' => 'lead_source_id', 'business_line' => 'business_line_id', 'level' => 'lead_level_id', 'priority' => 'lead_priority_id', 'team' => 'sales_team_id'] as $key => $column) {
            if ($this->filterString($key) !== '') {
                $query->where('leads.'.$column, (int) $this->filterString($key));
            }
        }

        if ($this->filterString('service') !== '') {
            $query->whereHas('services', fn (Builder $query) => $query->where('service_id', (int) $this->filterString('service')));
        }

        match ($this->filterString('assigned')) {
            '' => null,
            'me' => $query->where('leads.assigned_to', $this->actor()->id),
            'none' => $query->whereNull('leads.assigned_to'),
            default => $query->where('leads.assigned_to', (int) $this->filterString('assigned')),
        };

        if ($this->filterString('location') !== '') {
            $this->whereInLocation($query, 'leads.location_id', (int) $this->filterString('location'));
        }

        if ($from = $this->validDate($this->filters['date_from'] ?? null)) {
            $query->whereDate('leads.lead_date', '>=', $from);
        }

        if ($to = $this->validDate($this->filters['date_to'] ?? null)) {
            $query->whereDate('leads.lead_date', '<=', $to);
        }

        match ($this->filterString('follow_up')) {
            'today' => $query->whereBetween('leads.next_follow_up_at', [today()->startOfDay(), today()->endOfDay()]),
            'overdue' => $query->where('leads.next_follow_up_at', '<', now()),
            'none' => $query->whereNull('leads.next_follow_up_at'),
            default => null,
        };

        if ($this->filterString('stale') === '1') {
            $query->stale($this->staleDays());
        }

        match ($this->filterString('converted')) {
            '1' => $query->whereNotNull('leads.converted_customer_id'),
            '0' => $query->whereNull('leads.converted_customer_id'),
            default => null,
        };
    }

    /**
     * @return list<int>
     */
    private function statusIds(): array
    {
        return array_values(array_filter(array_map('intval', array_filter($this->statusFilter, 'is_string'))));
    }

    /**
     * The filtered open-lead query the Kanban and the mobile chip bar share: every filter
     * except the status chips and the closed-state filters.
     *
     * @return Builder<Lead>
     */
    private function pipelineQuery(): Builder
    {
        [$statusFilter, $filters] = [$this->statusFilter, $this->filters];
        $this->statusFilter = [];
        $this->filters = [...$filters, 'state' => 'open', 'closed_month' => ''];

        $base = $this->filteredQuery();

        [$this->statusFilter, $this->filters] = [$statusFilter, $filters];

        /** @var Builder<Lead> $base */
        return $base;
    }

    /**
     * Lead count and Σ expected value per open status.
     *
     * @return Collection<int|string, \stdClass>
     */
    private function statusTotals(): Collection
    {
        return $this->pipelineQuery()->reorder()->toBase()
            ->selectRaw('leads.lead_status_id as status_id, COUNT(*) as lead_count, COALESCE(SUM(leads.expected_value), 0) as value_total')
            ->groupBy('leads.lead_status_id')
            ->get()->keyBy('status_id');
    }

    /**
     * Open lead counts per status for the mobile chip bar.
     *
     * @return array<int|string, int>
     */
    public function statusCounts(): array
    {
        return $this->statusTotals()->map(fn (object $row): int => (int) $row->lead_count)->all();
    }

    /**
     * Kanban columns: open statuses in order, each with its cards, count and Σ expected value
     * (docs/03 §5.1). The status filter is ignored here; every other filter applies.
     *
     * @return list<array{id: int, code: string, name: string, color: ?string, count: int, total: string, leads: EloquentCollection<int, Lead>}>
     */
    public function kanbanColumns(): array
    {
        $base = $this->pipelineQuery();
        $totals = $this->statusTotals();

        return array_values(LeadStatus::query()->active()->open()->ordered()->get()->map(fn (LeadStatus $status): array => [
            'id' => $status->id,
            'code' => $status->code,
            'name' => $status->name,
            'color' => $status->color,
            'count' => (int) ($totals[$status->id]->lead_count ?? 0),
            'total' => number_format((float) ($totals[$status->id]->value_total ?? 0), 2, '.', ''),
            'leads' => (clone $base)->where('leads.lead_status_id', $status->id)->limit(self::KANBAN_CARDS_PER_COLUMN)->get(),
        ])->values()->all());
    }

    public function moveLead(string $leadId, int $position, string $statusId, ChangeLeadStatus $changeLeadStatus): void
    {
        $lead = Lead::query()->visibleTo($this->actor(), 'crm.leads')->find((int) $leadId);

        if ($lead === null || (int) $statusId === $lead->lead_status_id) {
            return;
        }

        try {
            $changeLeadStatus->handle($this->actor(), $lead, (int) $statusId);
        } catch (ValidationException $exception) {
            if (array_key_exists('follow_up', $exception->errors())) {
                $this->dispatch('crm-change-status', lead: $lead->lead_number, mode: 'status', status: (int) $statusId);

                return;
            }

            $this->dispatch('toast', type: 'error', description: (string) collect($exception->errors())->flatten()->first());
        } catch (AuthorizationException) {
            $this->dispatch('toast', type: 'error', description: __('You cannot change this lead.'));
        }
    }

    public function bulkAssign(AssignLead $assignLead): void
    {
        $this->authorize('crm.leads.assign');

        $this->runBulk(fn (Lead $lead) => $assignLead->handle($this->actor(), $lead, $this->bulkAssigneeId === '' ? null : (int) $this->bulkAssigneeId));
    }

    public function bulkChangeStatus(ChangeLeadStatus $changeLeadStatus): void
    {
        $this->authorize('crm.leads.update');

        $this->runBulk(fn (Lead $lead) => $changeLeadStatus->handle($this->actor(), $lead, (int) $this->bulkStatusId));
    }

    /**
     * Runs the change on every selected lead the user can see; failures are counted, not fatal.
     *
     * @param  Closure(Lead): mixed  $change
     */
    private function runBulk(Closure $change): void
    {
        $done = 0;
        $leads = Lead::query()->visibleTo($this->actor(), 'crm.leads')->whereKey(array_map('intval', $this->selected))->get();
        $skipped = count($this->selected) - $leads->count();

        foreach ($leads as $lead) {
            try {
                $change($lead);
                $done++;
            } catch (ValidationException|AuthorizationException) {
                $skipped++;
            }
        }

        $this->reset('selected', 'bulkAssigneeId', 'bulkStatusId');
        $this->dispatch('toast', type: $skipped > 0 ? 'warning' : 'success', description: __(':done updated, :skipped skipped.', ['done' => $done, 'skipped' => $skipped]));
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('crm.leads.export');

        return ListingExport::download('leads', $this->filteredQuery(), [
            'Lead #' => 'lead_number',
            'Date' => fn (Lead $lead): string => $lead->lead_date->format('d-M-Y'),
            'Name' => 'name',
            'Company' => 'company_name',
            'Phone' => 'phone',
            'Source' => 'source.name',
            'Business line' => 'businessLine.name',
            'Services' => fn (Lead $lead): string => $lead->services->pluck('service.name')->implode(', '),
            'Level' => 'level.name',
            'Status' => 'status.name',
            'Priority' => 'priority.name',
            'Assigned to' => 'assignee.name',
            'Team' => 'team.name',
            'Expected value' => 'expected_value',
            'Next follow-up' => fn (Lead $lead): ?string => $lead->next_follow_up_at?->format('d-M-Y H:i'),
            'Last activity' => fn (Lead $lead): ?string => $lead->last_activity_at?->format('d-M-Y H:i'),
        ]);
    }

    #[On('crm-lead-updated')]
    public function refreshList(): void {}

    public function staleDays(): int
    {
        return (int) Settings::get('crm.stale_lead_days', 14);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(AssignLead $assignLead): View
    {
        if (! in_array($this->view, ['list', 'kanban'], true)) {
            $this->view = 'list';
        }

        return view('livewire.crm.leads.index', [
            'rows' => $this->view === 'list' ? $this->paginatedRows() : null,
            'mobileRows' => $this->mobileRows(),
            'columns' => $this->view === 'kanban' ? $this->kanbanColumns() : [],
            'statusCounts' => $this->statusCounts(),
            'statuses' => LeadStatus::query()->active()->ordered()->get(['id', 'name', 'code', 'is_closed']),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'teams' => SalesTeam::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'assignees' => $this->actor()->can('crm.leads.assign') ? $assignLead->assignableUsers($this->actor()) : collect(),
            'staleCutoff' => now()->subDays($this->staleDays()),
        ]);
    }
}
