@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $qty = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    $manual = $measurement_formula === 'manual';
@endphp
<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$entry ? $entry->mb_number : __('New measurement')"
        :description="__('Executed quantity, measured on site against the BOQ.')"
        :cancel-url="$entry ? route('site.mb.show', $entry) : route('site.mb.index')"
        :submit-label="__('Save')">
        <x-slot:alerts>
            @foreach (['project', 'entry'] as $key)
                @if ($errors->has($key))
                    <x-ui.alert variant="destructive"><x-lucide-circle-alert /><x-ui.alert-description>{{ $errors->first($key) }}</x-ui.alert-description></x-ui.alert>
                @endif
            @endforeach
        </x-slot:alerts>

        <x-shell.form-section :title="__('What was measured')">
            <x-ui.field>
                <x-ui.field-label for="project_id">{{ __('Project') }} *</x-ui.field-label>
                @if ($entry)
                    <p class="text-sm"><span class="font-mono">{{ $entry->project->project_number }}</span> · {{ $entry->project->name }}</p>
                @else
                    <x-ui.select native id="project_id" wire:model.live="project_id" class="{{ $input }}">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($projects as $option)
                            <option value="{{ $option->id }}">{{ $option->project_number }} — {{ $option->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('project_id')" />
                @endif
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="estimate_line_id">{{ __('BOQ line') }}</x-ui.field-label>
                <x-ui.select native id="estimate_line_id" wire:model.live="estimate_line_id" class="{{ $input }}">
                    <option value="">{{ __('Not on the BOQ') }}</option>
                    @foreach ($boqLines as $line)
                        <option value="{{ $line->id }}">{{ $line->line_no }} · {{ \Illuminate\Support\Str::limit($line->description, 50) }} ({{ $qty($line->quantity) }} {{ $line->unit->symbol }})</option>
                    @endforeach
                </x-ui.select>
                <x-ui.field-description>{{ __('Lines of the project\'s approved estimates.') }}</x-ui.field-description>
                <x-ui.field-error :messages="$errors->get('estimate_line_id')" />
            </x-ui.field>
            @unless ($estimate_line_id)
                <x-ui.field>
                    <x-ui.field-label for="work_item_id">{{ __('Work item') }}</x-ui.field-label>
                    <x-ui.select native id="work_item_id" wire:model.live="work_item_id" class="{{ $input }}">
                        <option value="">—</option>
                        @foreach ($workItems as $item)
                            <option value="{{ $item->id }}">{{ $item->code }} · {{ $item->name }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            @endunless
            <x-ui.field>
                <x-ui.field-label for="description">{{ __('Description') }} *</x-ui.field-label>
                <x-ui.textarea id="description" wire:model="description" rows="2" class="text-base md:text-sm" />
                <x-ui.field-error :messages="$errors->get('description')" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="location">{{ __('Floor / location') }}</x-ui.field-label>
                <x-ui.input id="location" wire:model="location" class="{{ $input }}" />
                <x-ui.field-error :messages="$errors->get('location')" />
            </x-ui.field>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Measurement')">
            <x-ui.field>
                <x-ui.field-label for="measurement_formula">{{ __('Formula') }}</x-ui.field-label>
                <x-ui.select native id="measurement_formula" wire:model.live="measurement_formula" class="{{ $input }}">
                    @foreach ($formulas as $formula)
                        <option value="{{ $formula->value }}">{{ $formula->label() }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            @if ($manual)
                <x-ui.field>
                    <x-ui.field-label for="quantity">{{ __('Quantity') }} *</x-ui.field-label>
                    <x-ui.input id="quantity" wire:model.live.debounce.500ms="quantity" inputmode="decimal" class="{{ $input }} text-end tabular-nums" />
                    <x-ui.field-error :messages="$errors->get('quantity')" />
                </x-ui.field>
            @else
                <div class="grid grid-cols-4 gap-2">
                    @foreach (['nos' => __('Nos'), 'length' => __('L'), 'width' => __('W'), 'height' => __('H')] as $dimension => $label)
                        <x-ui.field>
                            <x-ui.field-label for="{{ $dimension }}">{{ $label }}</x-ui.field-label>
                            <x-ui.input id="{{ $dimension }}" wire:model.live.debounce.500ms="{{ $dimension }}" inputmode="decimal" class="{{ $input }} text-end tabular-nums" />
                        </x-ui.field>
                    @endforeach
                </div>
                <x-ui.field-error :messages="[...$errors->get('nos'), ...$errors->get('length'), ...$errors->get('width'), ...$errors->get('height'), ...$errors->get('quantity')]" />
            @endif
            <div class="grid grid-cols-2 gap-3">
                <x-ui.field>
                    <x-ui.field-label for="unit_id">{{ __('Unit') }} *</x-ui.field-label>
                    <x-ui.select native id="unit_id" wire:model="unit_id" class="{{ $input }}">
                        <option value="">—</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->symbol }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get('unit_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="rate">{{ __('Rate') }} *</x-ui.field-label>
                    <x-ui.input id="rate" wire:model="rate" inputmode="decimal" class="{{ $input }} text-end tabular-nums" :readonly="$rateLocked" />
                    @if ($rateLocked)<x-ui.field-description>{{ __('Only a project manager changes the rate.') }}</x-ui.field-description>@endif
                    <x-ui.field-error :messages="$errors->get('rate')" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="remarks">{{ __('Remarks') }}</x-ui.field-label>
                <x-ui.input id="remarks" wire:model="remarks" class="{{ $input }}" />
            </x-ui.field>
        </x-shell.form-section>

        <x-slot:aside>
            @if ($progress)
                @php($tone = ['ok' => 'border-success', 'warn' => 'border-warning', 'block' => 'border-destructive'][$progress['state']])
                <x-shell.form-section :title="__('BOQ progress')">
                    <div class="flex flex-col gap-2 rounded-md border-s-4 {{ $tone }} ps-3 text-sm" data-test="mb-progress">
                        <div class="flex justify-between"><span>{{ __('BOQ quantity') }}</span><span class="tabular-nums">{{ $qty($progress['boq']) }}</span></div>
                        <div class="flex justify-between"><span>{{ __('Measured before') }}</span><span class="tabular-nums">{{ $qty($progress['previous']) }}</span></div>
                        <div class="flex justify-between"><span>{{ __('This entry') }}</span><span class="tabular-nums">{{ $qty($progress['this']) }}</span></div>
                        <div class="flex justify-between font-semibold"><span>{{ __('Cumulative') }}</span><span class="tabular-nums">{{ $qty($progress['cumulative']) }} ({{ $progress['pct'] !== null ? round((float) $progress['pct'], 1) : '—' }}%)</span></div>
                        <x-ui.progress :value="min(100, (float) ($progress['pct'] ?? 0))" class="h-1.5" />
                        @if ($progress['state'] === 'warn')
                            <p class="text-warning">{{ __('Above the BOQ quantity, within the :pct% margin.', ['pct' => $progress['limit_pct']]) }}</p>
                        @elseif ($progress['state'] === 'block')
                            <p class="text-destructive">{{ __('More than :pct% above the BOQ; this will be refused.', ['pct' => $progress['limit_pct']]) }}</p>
                        @endif
                    </div>
                </x-shell.form-section>
            @endif
            <x-shell.form-section :title="__('Measurement Book')">
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field>
                        <x-ui.field-label for="mb_book_no">{{ __('Book no.') }}</x-ui.field-label>
                        <x-ui.input id="mb_book_no" wire:model="mb_book_no" class="{{ $input }}" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="mb_page_no">{{ __('Page no.') }}</x-ui.field-label>
                        <x-ui.input id="mb_page_no" wire:model="mb_page_no" class="{{ $input }}" />
                    </x-ui.field>
                </div>
                <x-ui.field>
                    <x-ui.field-label for="measured_on">{{ __('Measured on') }} *</x-ui.field-label>
                    <x-ui.input type="date" id="measured_on" wire:model="measured_on" class="{{ $input }}" max="{{ today()->toDateString() }}" />
                    <x-ui.field-error :messages="$errors->get('measured_on')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="measured_by">{{ __('Measured by') }} *</x-ui.field-label>
                    <x-employee-select id="measured_by" wire:model="measured_by" :include="$entry?->measured_by" :placeholder="__('Choose…')" />
                    <x-ui.field-error :messages="$errors->get('measured_by')" />
                </x-ui.field>
                @unless ($entry)
                    <p class="text-sm text-muted-foreground">{{ __('Add photos from the entry page after saving.') }}</p>
                @endunless
            </x-shell.form-section>
        </x-slot:aside>
    </x-shell.form-screen>
</div>
