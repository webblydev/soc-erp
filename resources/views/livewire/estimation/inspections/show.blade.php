@php
    use App\Modules\Estimation\Models\InspectionStatus;

    $user = auth()->user();
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $status = $inspection->status->code;
    $canUpdate = $user->can('update', $inspection);

    $actions = array_values(array_filter([
        $status !== InspectionStatus::CLOSED && $canUpdate ? ['label' => __('Edit'), 'icon' => 'pencil', 'href' => route('site.inspections.edit', $inspection), 'modal' => true] : null,
        $status === InspectionStatus::DRAFT && $canUpdate ? ['label' => __('Submit'), 'icon' => 'send', 'click' => '$wire.submit()', 'primary' => true] : null,
        $status !== InspectionStatus::CLOSED && $canUpdate ? ['label' => __('Add finding'), 'icon' => 'plus', 'click' => "\$dispatch('open-sheet-finding-add')", 'primary' => $status === InspectionStatus::SUBMITTED] : null,
        $status === InspectionStatus::SUBMITTED && $user->can('close', $inspection) ? ['label' => __('Close inspection'), 'icon' => 'circle-check', 'click' => '$wire.close()'] : null,
        $user->can('print', $inspection) ? ['label' => __('Print report'), 'icon' => 'printer', 'href' => route('site.inspections.print', $inspection), 'external' => true] : null,
        $status === InspectionStatus::DRAFT && $user->can('delete', $inspection) ? ['label' => __('Delete'), 'icon' => 'trash-2', 'click' => "\$dispatch('open-sheet-inspection-delete')", 'destructive' => true] : null,
    ]));
    $primary = collect($actions)->firstWhere('primary', true);
@endphp

<x-slot:actions>
    <x-ui.button variant="ghost" size="icon" class="size-11" x-on:click="$dispatch('open-sheet-inspection-actions')" :aria-label="__('Inspection actions')">
        <x-lucide-ellipsis-vertical class="size-5" />
    </x-ui.button>
</x-slot:actions>

<div class="flex flex-col gap-6 pb-24 md:pb-0">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 flex-col gap-1">
            <span class="font-mono text-sm text-muted-foreground">{{ $inspection->inspection_number }}</span>
            <h1 class="text-xl font-semibold tracking-tight md:text-2xl">{{ $inspection->type->name }} · {{ $inspection->inspection_date->format('d-M-Y') }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-ui.badge :tone="$inspection->status->color ?? 'neutral'" class="text-sm">{{ $inspection->status->name }}</x-ui.badge>
                <a data-detail-modal href="{{ route('projects.projects.show', ['project' => $inspection->project, 'tab' => 'site']) }}" wire:navigate class="font-medium hover:underline"><span class="font-mono">{{ $inspection->project->project_number }}</span> · {{ $inspection->project->name }}</a>
            </div>
        </div>
        @include('livewire.estimation.partials.action-buttons', ['actions' => $actions])
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Open findings') }}</span>
            <span class="text-lg font-semibold tabular-nums">{{ $figures['open'] }} / {{ $figures['total'] }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('High or critical open') }}</span>
            <span @class(['text-lg font-semibold tabular-nums', 'text-destructive' => $figures['serious'] > 0])>{{ $figures['serious'] }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Days since visit') }}</span>
            <span class="text-lg font-semibold tabular-nums">{{ $figures['days'] }}</span>
        </x-ui.card>
        <x-ui.card class="gap-1 p-4">
            <span class="text-sm text-muted-foreground">{{ __('Project engineer') }}</span>
            <span class="truncate text-sm font-medium">{{ $inspection->engineerName() ?? '—' }}</span>
        </x-ui.card>
    </div>

    <x-ui.card class="p-4 md:p-6">
        <x-ui.description-list>
            <x-ui.description-item :term="__('Time')">{{ collect([$inspection->start_time ? substr($inspection->start_time, 0, 5) : null, $inspection->end_time ? substr($inspection->end_time, 0, 5) : null])->filter()->implode(' – ') ?: '—' }}</x-ui.description-item>
            <x-ui.description-item :term="__('Site')">{{ $inspection->site_address ?: '—' }}</x-ui.description-item>
            <x-ui.description-item :term="__('Contractor')">{{ $inspection->contractor_name ?: '—' }}</x-ui.description-item>
            <x-ui.description-item :term="__('Permittee')">{{ $inspection->permittee_name ?: '—' }}</x-ui.description-item>
            <x-ui.description-item :term="__('Field office phone')">{{ $inspection->field_office_phone ?: '—' }}</x-ui.description-item>
            <x-ui.description-item :term="__('Client representative')">{{ $inspection->client_representative ?: '—' }}</x-ui.description-item>
            <x-ui.description-item :term="__('Weather / workers')">{{ collect([$inspection->weather, $inspection->workers_on_site !== null ? trans_choice(':count worker|:count workers', $inspection->workers_on_site) : null])->filter()->implode(' · ') ?: '—' }}</x-ui.description-item>
            @if ($inspection->work_progress_summary)
                <x-ui.description-item :term="__('Work progress')">{{ $inspection->work_progress_summary }}</x-ui.description-item>
            @endif
        </x-ui.description-list>
    </x-ui.card>

    <div class="flex flex-col gap-3">
        <h2 class="text-base font-semibold">{{ __('Findings') }}</h2>
        @forelse ($inspection->findings as $finding)
            @php
                $canMove = $status !== InspectionStatus::DRAFT && $user->can($finding->status->is_closed ? 'reopen' : 'changeStatus', $finding);
                $photos = $finding->attachments->filter(fn ($attachment) => str_starts_with($attachment->mime_type, 'image/'));
            @endphp
            <x-ui.card class="gap-3 p-4" wire:key="finding-card-{{ $finding->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex min-w-0 flex-col gap-1">
                        <span class="font-medium">{{ $finding->location ?: __('Finding') }}</span>
                        <p class="text-sm">{{ $finding->description }}</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-1">
                        <x-ui.badge :tone="$finding->severity->color ?? 'neutral'" class="text-sm">{{ $finding->severity->name }}</x-ui.badge>
                        <x-ui.badge :tone="$finding->status->color ?? 'neutral'" class="text-sm">{{ $finding->status->name }}</x-ui.badge>
                        @if ($finding->isOverdue())<x-ui.badge tone="danger" class="text-sm">{{ __('Overdue') }}</x-ui.badge>@endif
                    </div>
                </div>
                @if ($finding->finding)<p class="text-sm text-muted-foreground">{{ $finding->finding }}</p>@endif
                @if ($finding->action_required)<p class="text-sm"><span class="font-medium">{{ __('Action') }}:</span> {{ $finding->action_required }}</p>@endif
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                    @if ($finding->category)<span>{{ $finding->category->name }}</span>@endif
                    @if ($finding->responsibleName())<span>{{ __('Responsible') }}: {{ $finding->responsibleName() }}</span>@endif
                    @if ($finding->due_date)<span>{{ __('Due') }} {{ $finding->due_date->format('d-M-Y') }}</span>@endif
                    @if ($finding->found_by_name)<span>{{ __('Found by') }} {{ $finding->found_by_name }}</span>@endif
                </div>
                @if ($finding->closure_note)
                    <p class="text-sm"><span class="font-medium">{{ __('Closed :date', ['date' => $finding->closed_on?->format('d-M-Y')]) }}:</span> {{ $finding->closure_note }}</p>
                @endif
                @if ($photos->isNotEmpty())
                    <div class="flex gap-2 overflow-x-auto">
                        @foreach ($photos as $photo)
                            <a href="{{ $photo->downloadUrl() }}" target="_blank" class="shrink-0" wire:key="photo-{{ $photo->id }}">
                                <img src="{{ $photo->downloadUrl() }}" alt="{{ $photo->title ?? __('Site photo') }}" class="size-20 rounded-md object-cover" loading="lazy">
                            </a>
                        @endforeach
                    </div>
                @endif
                <div class="flex flex-wrap gap-2">
                    @if ($canMove)
                        <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" wire:click="openStatus({{ $finding->id }})"><x-lucide-arrow-right-left /> {{ $finding->status->is_closed ? __('Reopen') : __('Change status') }}</x-ui.button>
                    @endif
                    @if ($canUpdate && $status !== InspectionStatus::CLOSED)
                        <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" wire:click="startPhoto({{ $finding->id }})"><x-lucide-camera /> {{ __('Add photo') }}</x-ui.button>
                    @endif
                </div>
            </x-ui.card>
        @empty
            <p class="py-6 text-center text-sm text-muted-foreground">{{ __('No findings recorded.') }}</p>
        @endforelse
    </div>

    <livewire:foundation.attachments :model="$inspection" :key="'attachments-'.$inspection->id" />
    <livewire:foundation.history :model="$inspection" :key="'history-'.$inspection->id" />

    @if ($primary)
        <div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 border-t bg-background px-4 py-3 md:hidden">
            <x-ui.button class="h-11 w-full" x-on:click="{{ $primary['click'] }}"><x-dynamic-component :component="'lucide-'.$primary['icon']" /> {{ $primary['label'] }}</x-ui.button>
        </div>
    @endif

    @include('livewire.estimation.partials.action-sheet', ['actions' => $actions, 'sheet' => 'inspection-actions', 'title' => __('Inspection actions'), 'description' => $inspection->inspection_number])

    <x-shell.sheet id="finding-status" :title="__('Finding status')" :description="$statusFinding ? ($statusFinding->location ?: $statusFinding->description) : __('Move the finding on.')">
        @if ($statusFinding)
            <x-ui.field>
                <x-ui.field-label for="finding-status-code">{{ __('New status') }}</x-ui.field-label>
                <x-ui.select native id="finding-status-code" wire:model.live="statusCode" class="{{ $input }}">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($statusTargets as $target)
                        <option value="{{ $target->code }}">{{ $target->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="finding-status-note">{{ __('Closure note') }}</x-ui.field-label>
                <x-ui.textarea id="finding-status-note" wire:model="statusNote" rows="3" class="text-base md:text-sm" />
                <x-ui.field-description>{{ __('Needed when the finding is resolved or accepted.') }}</x-ui.field-description>
                <x-ui.field-error :messages="$errors->get('statusNote')" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="finding-status-photo">{{ __('After photo') }}</x-ui.field-label>
                <input type="file" id="finding-status-photo" wire:model="statusPhoto" accept="image/*" capture="environment" class="min-h-11 text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('statusPhoto')" />
            </x-ui.field>
        @endif
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-finding-status')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button wire:click="changeStatus" wire:loading.attr="disabled">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    <x-shell.sheet id="finding-photo" :title="__('Add photo')" :description="__('Take a photo or pick one from the gallery.')">
        <x-ui.field>
            <x-ui.field-label for="finding-photo-file">{{ __('Photo') }}</x-ui.field-label>
            <input type="file" id="finding-photo-file" wire:model="photo" accept="image/*" capture="environment" class="min-h-11 text-base md:text-sm" />
            <x-ui.field-error :messages="$errors->get('photo')" />
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-finding-photo')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button wire:click="uploadPhoto" wire:loading.attr="disabled">{{ __('Upload') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    @if ($canUpdate && $status !== InspectionStatus::CLOSED)
        <x-shell.sheet id="finding-add" :title="__('Add finding')" :description="__('High and critical findings need someone responsible and a due date.')">
            @include('livewire.estimation.inspections.finding-fields', ['prefix' => 'newFinding', 'errorPrefix' => 'finding.0', 'idPrefix' => 'new-finding'])
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-finding-add')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button wire:click="addFinding">{{ __('Add finding') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endif

    @can('delete', $inspection)
        <x-shell.confirm id="inspection-delete" :title="__('Delete inspection?')" :description="__('Only a draft inspection can be deleted.')">
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-inspection-delete')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="destructive" wire:click="delete">{{ __('Delete') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.confirm>
    @endcan
</div>
