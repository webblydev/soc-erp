@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $date = fn ($value) => $value?->format('d-M-Y') ?? '—';
    $money = fn ($value) => \App\Support\Money::format($value);
    $triggerCodes = $triggers->pluck('code', 'id');
    $isDraft = $contract?->isDraft() ?? false;
    $isSigned = $contract?->isSigned() ?? false;
    $hasDraftAmendment = $contract?->amendments->contains(fn ($amendment) => $amendment->isDraft()) ?? false;
@endphp

<div class="flex flex-col gap-4">
    <x-ui.field-error :messages="[...$errors->get('contract'), ...$errors->get('schedule'), ...$errors->get('amendment')]" />

    <x-ui.card class="p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h2 class="text-base font-semibold">{{ __('Contract') }}</h2>
                @if ($contract)
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <x-ui.badge :tone="$contract->status->color ?? 'neutral'" class="text-sm">{{ $contract->status->name }}</x-ui.badge>
                        @if ($contract->contract_number)<span class="font-mono">{{ $contract->contract_number }}</span>@endif
                    </div>
                @else
                    <p class="text-sm text-muted-foreground">{{ __('No contract yet.') }}</p>
                @endif
            </div>
            @if ($canManage)
                <div class="flex flex-wrap gap-2">
                    @if (! $contract || ! $contract->isTerminated())
                        <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" x-on:click="$dispatch('open-sheet-contract-form')">
                            <x-lucide-pencil /> {{ $contract ? ($isDraft ? __('Edit') : __('Edit terms')) : __('Create contract') }}
                        </x-ui.button>
                    @endif
                    @if ($isDraft)
                        <x-ui.button size="sm" class="h-11 md:h-8" wire:click="sign" wire:confirm="{{ __('Sign the contract? Services and the deed amount lock after signing.') }}"><x-lucide-signature /> {{ __('Sign') }}</x-ui.button>
                    @endif
                    @if ($isSigned && ! $hasDraftAmendment)
                        <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" :href="route('projects.projects.amendments.create', $project)" wire:navigate><x-lucide-file-plus /> {{ __('New amendment') }}</x-ui.button>
                    @endif
                    @if ($contract && ! $contract->isTerminated())
                        <x-ui.button size="sm" variant="outline" class="h-11 text-destructive md:h-8" x-on:click="$dispatch('open-sheet-contract-terminate')"><x-lucide-ban /> {{ __('Terminate') }}</x-ui.button>
                    @endif
                </div>
            @endif
        </div>

        @if ($contract)
            <x-ui.description-list class="mt-2">
                <x-ui.description-item :term="__('Agreement date')">{{ $date($contract->agreement_date) }}</x-ui.description-item>
                <x-ui.description-item :term="__('Deed amount')"><span class="tabular-nums">{{ $money($contract->deed_amount) }}</span></x-ui.description-item>
                <x-ui.description-item :term="__('VAT')">{{ $contract->vat_inclusive ? __('Inclusive') : __('Exclusive') }}</x-ui.description-item>
                <x-ui.description-item :term="__('Advance / retention')">{{ $contract->advance_pct !== null ? rtrim(rtrim($contract->advance_pct, '0'), '.').' %' : '—' }} / {{ $contract->retention_pct !== null ? rtrim(rtrim($contract->retention_pct, '0'), '.').' %' : '—' }}</x-ui.description-item>
                <x-ui.description-item :term="__('Defect liability')">{{ $contract->defect_liability_months !== null ? trans_choice(':count month|:count months', $contract->defect_liability_months) : '—' }}</x-ui.description-item>
                <x-ui.description-item :term="__('Signed by customer')">{{ $contract->signed_by_customer ?? '—' }}</x-ui.description-item>
                <x-ui.description-item :term="__('Signed for SOC')">{{ $contract->signedBy?->name ?? '—' }}@if ($contract->signed_at) · {{ $contract->signed_at->format('d-M-Y') }}@endif</x-ui.description-item>
                @if ($contract->isTerminated())
                    <x-ui.description-item :term="__('Terminated')">{{ $contract->terminated_at?->format('d-M-Y') }} · {{ $contract->termination_reason }}</x-ui.description-item>
                @endif
            </x-ui.description-list>
            @if ($contract->terms)
                <p class="mt-3 whitespace-pre-line text-sm">{{ $contract->terms }}</p>
            @endif
        @endif
    </x-ui.card>

    @if ($contract && $contract->amendments->isNotEmpty())
        <x-ui.card class="p-4 md:p-6">
            <h2 class="text-base font-semibold">{{ __('Amendments') }}</h2>
            <x-ui.item-group class="gap-2">
                @foreach ($contract->amendments as $amendment)
                    <x-ui.item variant="outline" class="flex-col items-stretch gap-2 md:flex-row md:items-center" wire:key="amendment-{{ $amendment->id }}">
                        <x-ui.item-content class="min-w-0">
                            <x-ui.item-title class="text-sm">{{ __('Amendment :no', ['no' => $amendment->amendment_no]) }} · {{ $amendment->amendment_date->format('d-M-Y') }}</x-ui.item-title>
                            <x-ui.item-description class="text-sm">{{ $amendment->reason }}</x-ui.item-description>
                            @if (! $amendment->isDraft())
                                <x-ui.item-description class="text-sm tabular-nums">
                                    {{ __('Change') }}: {{ $money($amendment->value_change) }} · {{ __('New deed') }}: {{ $money($amendment->new_deed_amount) }} · {{ $amendment->approver?->name }}
                                </x-ui.item-description>
                            @endif
                        </x-ui.item-content>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge :tone="$amendment->isDraft() ? 'warning' : 'success'" class="text-sm">{{ $amendment->isDraft() ? __('Draft') : __('Approved') }}</x-ui.badge>
                            @if ($amendment->isDraft() && $canManage)
                                <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" :href="route('projects.projects.amendments.edit', [$project, $amendment])" wire:navigate>{{ __('Edit') }}</x-ui.button>
                                <x-ui.button size="sm" class="h-11 md:h-8" wire:click="approveAmendment({{ $amendment->id }})" wire:confirm="{{ __('Approve the amendment? The services and the deed amount change.') }}">{{ __('Approve') }}</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" class="h-11 md:h-8" wire:click="deleteAmendment({{ $amendment->id }})" wire:confirm="{{ __('Delete this draft amendment?') }}" :aria-label="__('Delete')"><x-lucide-trash-2 /></x-ui.button>
                            @endif
                        </div>
                    </x-ui.item>
                @endforeach
            </x-ui.item-group>
        </x-ui.card>
    @endif

    <x-ui.card class="p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h2 class="text-base font-semibold">{{ __('Payment schedule') }}</h2>
                <p @class(['text-sm tabular-nums', 'text-destructive' => abs((float) $difference) > 1, 'text-muted-foreground' => abs((float) $difference) <= 1])>
                    {{ __('Scheduled :scheduled of :deed', ['scheduled' => $money($scheduled), 'deed' => $money($deed)]) }}
                    @if (abs((float) $difference) > 1) · {{ __('difference :amount', ['amount' => $money($difference)]) }} @endif
                </p>
            </div>
            @if ($canManage && ! $contract?->isTerminated())
                <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" wire:click="editSchedule"><x-lucide-pencil /> {{ __('Edit schedule') }}</x-ui.button>
            @endif
        </div>
        <x-ui.item-group class="mt-3 gap-2">
            @forelse ($schedules as $line)
                <x-ui.item variant="outline" class="flex-col items-stretch gap-2 md:flex-row md:items-center" wire:key="schedule-{{ $line->id }}">
                    <x-ui.item-content class="min-w-0">
                        <x-ui.item-title class="text-sm">{{ $line->milestone_name }}</x-ui.item-title>
                        <x-ui.item-description class="text-sm">
                            {{ $line->trigger->name }}@if ($line->due_date) · {{ $line->due_date->format('d-M-Y') }}@endif
                            @if ($line->percent !== null) · {{ rtrim(rtrim($line->percent, '0'), '.') }} % @endif
                        </x-ui.item-description>
                    </x-ui.item-content>
                    <div class="flex items-center justify-between gap-3 md:justify-end">
                        <x-ui.badge :tone="$line->status->color ?? 'neutral'" class="text-sm">{{ $line->status->name }}</x-ui.badge>
                        <span class="text-sm font-medium tabular-nums">{{ $money($line->amount) }}</span>
                        @if ($canManage && $line->isPending())
                            <x-ui.button size="sm" variant="outline" class="h-11 md:h-8" wire:click="markDue({{ $line->id }})">{{ __('Mark due') }}</x-ui.button>
                        @endif
                    </div>
                </x-ui.item>
            @empty
                <p class="text-sm text-muted-foreground">{{ __('No milestones yet.') }}</p>
            @endforelse
        </x-ui.item-group>
    </x-ui.card>

    @if ($canManage)
        <x-shell.sheet id="contract-form" :title="$contract ? __('Edit contract') : __('Create contract')" :description="__('Agreement details. The deed amount follows the contract value until signing.')">
            <form id="contract-form-el" wire:submit="saveContract" class="flex flex-col gap-4">
                @if (! $contract || $isDraft)
                    <x-ui.field>
                        <x-ui.field-label for="contract-number">{{ __('Agreement / deed no.') }}</x-ui.field-label>
                        <x-ui.input id="contract-number" wire:model="contractForm.contract_number" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('contractForm.contract_number')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="contract-date">{{ __('Agreement date') }} *</x-ui.field-label>
                        <x-ui.input type="date" id="contract-date" wire:model="contractForm.agreement_date" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('contractForm.agreement_date')" />
                    </x-ui.field>
                    <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                        <x-ui.checkbox native id="contract-vat" wire:model="contractForm.vat_inclusive" value="1" />
                        <x-ui.field-label for="contract-vat" class="font-normal">{{ __('Amounts include VAT') }}</x-ui.field-label>
                    </x-ui.field>
                    <div class="grid grid-cols-2 gap-4">
                        <x-ui.field>
                            <x-ui.field-label for="contract-advance">{{ __('Advance %') }}</x-ui.field-label>
                            <x-ui.input id="contract-advance" wire:model="contractForm.advance_pct" inputmode="decimal" class="{{ $input }} tabular-nums" />
                            <x-ui.field-error :messages="$errors->get('contractForm.advance_pct')" />
                        </x-ui.field>
                        <x-ui.field>
                            <x-ui.field-label for="contract-retention">{{ __('Retention %') }}</x-ui.field-label>
                            <x-ui.input id="contract-retention" wire:model="contractForm.retention_pct" inputmode="decimal" class="{{ $input }} tabular-nums" />
                            <x-ui.field-error :messages="$errors->get('contractForm.retention_pct')" />
                        </x-ui.field>
                    </div>
                    <x-ui.field>
                        <x-ui.field-label for="contract-dlp">{{ __('Defect liability (months)') }}</x-ui.field-label>
                        <x-ui.input id="contract-dlp" wire:model="contractForm.defect_liability_months" inputmode="numeric" class="{{ $input }} tabular-nums" />
                        <x-ui.field-error :messages="$errors->get('contractForm.defect_liability_months')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="contract-customer-signatory">{{ __('Signed by (customer)') }}</x-ui.field-label>
                        <x-ui.input id="contract-customer-signatory" wire:model="contractForm.signed_by_customer" class="{{ $input }}" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="contract-company-signatory">{{ __('Signed for SOC') }}</x-ui.field-label>
                        <x-ui.select native id="contract-company-signatory" wire:model="contractForm.signed_by_company_user_id" class="{{ $input }}">
                            <option value="">{{ __('Whoever signs it here') }}</option>
                            @foreach ($users as $signatory)
                                <option value="{{ $signatory->id }}">{{ $signatory->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.field-error :messages="$errors->get('contractForm.signed_by_company_user_id')" />
                    </x-ui.field>
                @endif
                <x-ui.field>
                    <x-ui.field-label for="contract-terms">{{ __('Terms') }}</x-ui.field-label>
                    <x-ui.textarea id="contract-terms" wire:model="contractForm.terms" rows="5" class="text-base md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('contractForm.terms')" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-contract-form')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="contract-form-el">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>

        <x-shell.sheet id="contract-terminate" :title="__('Terminate contract')" :description="__('The contract ends. Services and milestones stay as they are.')">
            <form id="contract-terminate-form" wire:submit="terminate" class="flex flex-col gap-4">
                <x-ui.field>
                    <x-ui.field-label for="terminate-reason">{{ __('Reason') }} *</x-ui.field-label>
                    <x-ui.textarea id="terminate-reason" wire:model="terminationReason" rows="3" class="text-base md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('terminationReason')" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-contract-terminate')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="destructive" form="contract-terminate-form">{{ __('Terminate') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>

        <x-shell.sheet id="schedule-form" :title="__('Payment schedule')" :description="__('Milestones with an amount or a percent of the deed amount :deed.', ['deed' => $money($deed)])">
            <form id="schedule-form-el" wire:submit="saveSchedule" class="flex flex-col gap-3 pb-2">
                @foreach ($scheduleLines as $i => $line)
                    @php($code = $triggerCodes[(int) ($line['schedule_trigger_id'] ?? 0)] ?? null)
                    <x-ui.item variant="outline" class="flex-col items-stretch gap-3" wire:key="schedule-line-{{ $i }}">
                        <div class="flex gap-2">
                            <x-ui.input wire:model="scheduleLines.{{ $i }}.milestone_name" :placeholder="__('Milestone, e.g. Advance on signing')" class="{{ $input }} flex-1" :aria-label="__('Milestone')" />
                            <x-ui.button type="button" variant="ghost" size="icon" class="size-11 md:size-9" wire:click="removeScheduleLine({{ $i }})" :aria-label="__('Remove milestone')"><x-lucide-trash-2 /></x-ui.button>
                        </div>
                        <x-ui.field-error :messages="[...$errors->get('scheduleLines.'.$i.'.milestone_name'), ...$errors->get('scheduleLines.'.$i.'.id')]" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui.select native wire:model.live="scheduleLines.{{ $i }}.schedule_trigger_id" class="{{ $input }}" :aria-label="__('Trigger')">
                                @foreach ($triggers as $trigger)
                                    <option value="{{ $trigger->id }}">{{ $trigger->name }}</option>
                                @endforeach
                            </x-ui.select>
                            @if ($code === 'PHASE')
                                <x-ui.select native wire:model="scheduleLines.{{ $i }}.trigger_ref_id" class="{{ $input }}" :aria-label="__('Phase')">
                                    <option value="">{{ __('Choose phase…') }}</option>
                                    @foreach ($phases as $phase)
                                        <option value="{{ $phase->id }}">{{ $phase->name }}</option>
                                    @endforeach
                                </x-ui.select>
                            @elseif ($code === 'APPROVAL')
                                <x-ui.select native wire:model="scheduleLines.{{ $i }}.trigger_ref_id" class="{{ $input }}" :aria-label="__('Approval')">
                                    <option value="">{{ __('Choose approval…') }}</option>
                                    @foreach ($projectApprovals as $approval)
                                        <option value="{{ $approval->id }}">{{ $approval->type->name }}{{ $approval->reference_no ? ' · '.$approval->reference_no : '' }}</option>
                                    @endforeach
                                </x-ui.select>
                            @elseif ($code === 'TASK')
                                <x-ui.select native wire:model="scheduleLines.{{ $i }}.trigger_ref_id" class="{{ $input }}" :aria-label="__('Task')">
                                    <option value="">{{ __('Choose task…') }}</option>
                                    @foreach ($projectTasks as $task)
                                        <option value="{{ $task->id }}">{{ $task->task_number }} · {{ $task->title }}</option>
                                    @endforeach
                                </x-ui.select>
                            @else
                                <x-ui.input type="date" wire:model="scheduleLines.{{ $i }}.due_date" class="{{ $input }}" :aria-label="__('Due date')" />
                            @endif
                        </div>
                        <x-ui.field-error :messages="[...$errors->get('scheduleLines.'.$i.'.trigger_ref_id'), ...$errors->get('scheduleLines.'.$i.'.due_date')]" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui.input wire:model="scheduleLines.{{ $i }}.percent" inputmode="decimal" :placeholder="__('Percent')" class="{{ $input }} text-end tabular-nums" :aria-label="__('Percent')" />
                            <x-ui.input wire:model="scheduleLines.{{ $i }}.amount" inputmode="decimal" :placeholder="__('Amount')" class="{{ $input }} text-end tabular-nums" :aria-label="__('Amount')" />
                        </div>
                        <x-ui.field-error :messages="[...$errors->get('scheduleLines.'.$i.'.percent'), ...$errors->get('scheduleLines.'.$i.'.amount')]" />
                        @isset($line['status'])
                            <span class="text-sm text-muted-foreground">{{ $line['status'] }}</span>
                        @endisset
                    </x-ui.item>
                @endforeach
                <x-ui.button type="button" variant="outline" class="h-11 self-start md:h-9" wire:click="addScheduleLine"><x-lucide-plus /> {{ __('Add milestone') }}</x-ui.button>
                <p class="text-sm text-muted-foreground">{{ __('An amount wins over a percent; the other is worked out from the deed amount.') }}</p>
                <x-ui.field-error :messages="$errors->get('schedule')" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-schedule-form')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="schedule-form-el">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endif
</div>
