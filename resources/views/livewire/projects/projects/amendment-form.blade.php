@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
@endphp

<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$amendment ? __('Amendment :no', ['no' => $amendment->amendment_no]) : __('New amendment')"
        :description="__(':number · current contract value :value', ['number' => $project->project_number, 'value' => \App\Support\Money::format($project->contract_value)])"
        :cancel-url="route('projects.projects.show', ['project' => $project, 'tab' => 'contract'])"
        :submit-label="__('Save draft')">

        <x-ui.field-error :messages="$errors->get('amendment')" />

        <x-shell.form-section :title="__('Amendment')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="amendment_date">{{ __('Date') }} *</x-ui.field-label>
                    <x-ui.input type="date" id="amendment_date" wire:model="amendment_date" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('amendment_date')" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="reason">{{ __('Reason') }} *</x-ui.field-label>
                <x-ui.textarea id="reason" wire:model="reason" rows="2" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('reason')" />
            </x-ui.field>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Services after the amendment')" :description="__('Lines you remove are kept as cancelled once approved.')">
            @foreach ($services as $i => $line)
                <x-ui.item variant="outline" class="flex-col items-stretch gap-3" wire:key="amendment-line-{{ $i }}">
                    <div class="flex gap-2">
                        <x-ui.select native wire:model="services.{{ $i }}.service_id" class="{{ $input }} flex-1" :aria-label="__('Service')">
                            <option value="">{{ __('Choose service…') }}</option>
                            @foreach ($serviceOptions as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.button type="button" variant="ghost" size="icon" class="size-11 md:size-9" wire:click="removeService({{ $i }})" :aria-label="__('Remove service')"><x-lucide-trash-2 /></x-ui.button>
                    </div>
                    <x-ui.field-error :messages="$errors->get('services.'.$i.'.service_id')" />
                    <x-ui.input wire:model="services.{{ $i }}.description" :placeholder="__('Description')" class="{{ $input }}" :aria-label="__('Description')" />
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                        <x-ui.input wire:model.live.debounce.500ms="services.{{ $i }}.quantity" inputmode="decimal" :placeholder="__('Qty')" class="{{ $input }} text-end tabular-nums" :aria-label="__('Quantity')" />
                        <x-ui.select native wire:model="services.{{ $i }}.unit_id" class="{{ $input }}" :aria-label="__('Unit')">
                            <option value="">—</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->symbol }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input wire:model.live.debounce.500ms="services.{{ $i }}.rate" inputmode="decimal" :placeholder="__('Rate')" class="{{ $input }} text-end tabular-nums" :aria-label="__('Rate')" />
                        <x-ui.input wire:model.live.debounce.500ms="services.{{ $i }}.discount_amount" inputmode="decimal" :placeholder="__('Discount')" class="{{ $input }} text-end tabular-nums" :aria-label="__('Discount')" />
                        <x-ui.select native wire:model.live="services.{{ $i }}.project_service_status_id" class="{{ $input }} col-span-2 md:col-span-1" :aria-label="__('Status')">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <x-ui.field-error :messages="[...$errors->get('services.'.$i.'.quantity'), ...$errors->get('services.'.$i.'.rate'), ...$errors->get('services.'.$i.'.discount_amount')]" />
                </x-ui.item>
            @endforeach
            <x-ui.button type="button" variant="outline" class="h-11 self-start md:h-9" wire:click="addService"><x-lucide-plus /> {{ __('Add service') }}</x-ui.button>
            <div class="flex items-center justify-between border-t pt-4">
                <span class="text-sm font-medium">{{ __('New contract value') }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ \App\Support\Money::format($newValue) }}</span>
            </div>
        </x-shell.form-section>
    </x-shell.form-screen>
</div>
