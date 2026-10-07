@php
    $input = 'h-11 text-base md:h-9 md:text-sm';
    $cell = 'h-9 min-w-0 px-2 text-sm';
    $money = fn ($value) => $value === null ? '—' : \App\Support\Money::format($value, false);
    $qty = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    $hasWork = $kind?->has_work_lines ?? true;
    $hasMaterials = $kind?->has_material_lines ?? false;
    $unitSymbol = fn ($id) => $units->firstWhere('id', (int) $id)?->symbol ?? '';
    $tabOptions = array_values(array_filter([
        $hasWork ? ['value' => 'work', 'label' => __('Work sheet')] : null,
        $hasMaterials ? ['value' => 'materials', 'label' => __('Materials')] : null,
    ]));
    $currentTab = $hasWork ? $tab : 'materials';
    $editingLine = $editSection !== null ? ($sections[$editSection]['lines'][$editLine] ?? null) : null;
@endphp

<div>
    <x-shell.form-screen wire:submit="save"
        :heading="$estimate ? $estimate->estimate_number.' · '.$estimate->title : __('New estimate')"
        :description="__('Measurement sheet, rates and totals. Save as a draft, then submit for approval.')"
        :cancel-url="$estimate ? route('estimation.estimates.show', $estimate) : route('estimation.estimates.index')"
        :submit-label="__('Save draft')">
        <x-slot:alerts>
            @if ($errors->has('estimate') || $errors->has('sections') || $errors->has('material_lines'))
                <x-ui.alert variant="destructive">
                    <x-lucide-circle-alert />
                    <x-ui.alert-title>{{ __('The estimate was not saved') }}</x-ui.alert-title>
                    <x-ui.alert-description>{{ implode(' ', [...$errors->get('estimate'), ...$errors->get('sections'), ...$errors->get('material_lines')]) }}</x-ui.alert-description>
                </x-ui.alert>
            @endif
            @if ($estimate?->rejection_note && $estimate->status->code === 'REJECTED')
                <x-ui.alert>
                    <x-lucide-message-square-warning />
                    <x-ui.alert-title>{{ __('Rejected') }}</x-ui.alert-title>
                    <x-ui.alert-description>{{ $estimate->rejection_note }}</x-ui.alert-description>
                </x-ui.alert>
            @endif
        </x-slot:alerts>

        <x-shell.form-section :title="__('Estimate')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="project_id">{{ __('Project') }} *</x-ui.field-label>
                    @if ($estimate)
                        <p class="text-sm"><span class="font-mono">{{ $estimate->project->project_number }}</span> · {{ $estimate->project->name }}</p>
                    @else
                        <x-ui.select native id="project_id" wire:model="project_id" class="{{ $input }}">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($projects as $option)
                                <option value="{{ $option->id }}">{{ $option->project_number }} — {{ $option->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.field-error :messages="$errors->get('project_id')" />
                    @endif
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="estimate_kind_id">{{ __('Kind') }} *</x-ui.field-label>
                    @if ($estimate)
                        <p class="text-sm">{{ $kind?->name }}</p>
                    @else
                        <x-ui.select native id="estimate_kind_id" wire:model.live="estimate_kind_id" class="{{ $input }}">
                            @foreach ($kinds as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.field-error :messages="$errors->get('estimate_kind_id')" />
                    @endif
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="title">{{ __('Title') }} *</x-ui.field-label>
                <x-ui.input id="title" wire:model="title" class="{{ $input }}" :placeholder="__('e.g. Ground floor civil works')" />
                <x-ui.field-error :messages="$errors->get('title')" />
            </x-ui.field>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="estimate_date">{{ __('Date') }} *</x-ui.field-label>
                    <x-ui.input type="date" id="estimate_date" wire:model="estimate_date" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('estimate_date')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="site_address">{{ __('Site address') }}</x-ui.field-label>
                    <x-ui.input id="site_address" wire:model="site_address" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('site_address')" />
                </x-ui.field>
            </div>
        </x-shell.form-section>

        <x-shell.form-section :title="__('Lines')" :description="__('Quantities follow the formula; tick Deduct for openings.')">
            @if (count($tabOptions) > 1)
                <x-ui.segmented-control name="estimate-tab" wire:model.live="tab" :value="$currentTab" class="h-11 self-start md:h-9" :options="$tabOptions" />
            @endif

            @if ($currentTab === 'work')
                @foreach ($sections as $s => $section)
                    <div class="flex flex-col gap-3" wire:key="section-{{ $s }}">
                        <div class="flex items-center gap-2">
                            <x-ui.input wire:model.blur="sections.{{ $s }}.name" :placeholder="__('Section name (optional)')" class="{{ $input }} flex-1 font-medium" :aria-label="__('Section name')" />
                            <span class="hidden text-sm tabular-nums text-muted-foreground md:inline">{{ $money($preview['sections'][$s] ?? null) }}</span>
                            <x-ui.button type="button" variant="ghost" size="icon" class="size-11 md:size-9" wire:click="removeSection({{ $s }})" wire:confirm="{{ __('Remove this section and its lines?') }}" :aria-label="__('Remove section')"><x-lucide-trash-2 /></x-ui.button>
                        </div>

                        {{-- Desktop grid --}}
                        <div class="hidden overflow-x-auto rounded-md border md:block">
                            <table class="w-full min-w-[1400px] text-sm">
                                <thead class="bg-muted/50 text-start text-sm text-muted-foreground">
                                    <tr>
                                        <th class="px-2 py-2 text-start font-medium">{{ __('No.') }}</th>
                                        <th class="px-2 py-2 text-start font-medium">{{ __('Work item') }}</th>
                                        <th class="px-2 py-2 text-start font-medium">{{ __('Description') }}</th>
                                        <th class="px-2 py-2 text-start font-medium">{{ __('Level') }}</th>
                                        <th class="px-2 py-2 text-start font-medium">{{ __('Location') }}</th>
                                        <th class="px-2 py-2 text-start font-medium">{{ __('Formula') }}</th>
                                        <th class="px-2 py-2 text-end font-medium">{{ __('Nos') }}</th>
                                        <th class="px-2 py-2 text-end font-medium">{{ __('L') }}</th>
                                        <th class="px-2 py-2 text-end font-medium">{{ __('W') }}</th>
                                        <th class="px-2 py-2 text-end font-medium">{{ __('H') }}</th>
                                        <th class="px-2 py-2 font-medium">{{ __('Deduct') }}</th>
                                        <th class="px-2 py-2 text-start font-medium">{{ __('Unit') }}</th>
                                        <th class="px-2 py-2 text-end font-medium">{{ __('Qty') }}</th>
                                        <th class="px-2 py-2 text-end font-medium">{{ __('Rate') }}</th>
                                        <th class="px-2 py-2 text-end font-medium">{{ __('Amount') }}</th>
                                        <th class="px-2 py-2 text-start font-medium">{{ __('Category') }}</th>
                                        <th class="px-2 py-2 text-start font-medium">{{ __('Remarks') }}</th>
                                        <th class="px-2 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($section['lines'] as $l => $line)
                                        @php
                                            $key = "sections.{$s}.lines.{$l}";
                                            $live = $preview['lines']["{$s}.{$l}"] ?? ['quantity' => null, 'amount' => null];
                                            $manual = ($line['measurement_formula'] ?? '') === 'manual';
                                            $lineErrors = collect($errors->getMessages())->filter(fn ($messages, $field) => str_starts_with($field, $key.'.'))->flatten();
                                        @endphp
                                        <tr wire:key="line-{{ $s }}-{{ $l }}" @class(['border-t align-top', 'bg-destructive/5' => $line['deduction'] ?? false])
                                            x-on:keydown.enter.prevent="$wire.addLine({{ $s }}, {{ $l }})"
                                            x-on:keydown.ctrl.d.prevent="$wire.duplicateLine({{ $s }}, {{ $l }})"
                                            x-on:keydown.ctrl.arrow-up.prevent="$wire.moveLine({{ $s }}, {{ $l }}, -1)"
                                            x-on:keydown.ctrl.arrow-down.prevent="$wire.moveLine({{ $s }}, {{ $l }}, 1)">
                                            <td class="p-1"><x-ui.input wire:model.blur="{{ $key }}.line_no" class="{{ $cell }} w-16" :placeholder="__('auto')" :aria-label="__('Line no.')" /></td>
                                            <td class="p-1">
                                                <x-ui.select native wire:model.live="{{ $key }}.work_item_id" class="{{ $cell }} w-40" :aria-label="__('Work item')">
                                                    <option value="">—</option>
                                                    @foreach ($workItems as $item)
                                                        <option value="{{ $item->id }}">{{ $item->code }} · {{ \Illuminate\Support\Str::limit($item->name, 40) }}</option>
                                                    @endforeach
                                                </x-ui.select>
                                            </td>
                                            <td class="p-1"><x-ui.input wire:model.blur="{{ $key }}.description" class="{{ $cell }} w-64" :aria-label="__('Description')" /></td>
                                            <td class="p-1"><x-ui.input wire:model.blur="{{ $key }}.level" class="{{ $cell }} w-20" :aria-label="__('Level')" /></td>
                                            <td class="p-1"><x-ui.input wire:model.blur="{{ $key }}.location" class="{{ $cell }} w-24" :aria-label="__('Location')" /></td>
                                            <td class="p-1">
                                                <x-ui.select native wire:model.live="{{ $key }}.measurement_formula" class="{{ $cell }} w-32" :aria-label="__('Formula')">
                                                    @foreach ($formulas as $formula)
                                                        <option value="{{ $formula->value }}">{{ $formula->label() }}</option>
                                                    @endforeach
                                                </x-ui.select>
                                            </td>
                                            @foreach (['nos', 'length', 'width', 'height'] as $dimension)
                                                <td class="p-1"><x-ui.input wire:model.blur="{{ $key }}.{{ $dimension }}" inputmode="decimal" class="{{ $cell }} w-20 text-end tabular-nums" :disabled="$manual" :aria-label="__($dimension)" /></td>
                                            @endforeach
                                            <td class="p-1 text-center"><x-ui.checkbox wire:model.live="{{ $key }}.deduction" class="mt-2" :aria-label="__('Deduct')" /></td>
                                            <td class="p-1">
                                                <x-ui.select native wire:model.blur="{{ $key }}.unit_id" class="{{ $cell }} w-20" :aria-label="__('Unit')">
                                                    <option value="">—</option>
                                                    @foreach ($units as $unit)
                                                        <option value="{{ $unit->id }}">{{ $unit->symbol }}</option>
                                                    @endforeach
                                                </x-ui.select>
                                            </td>
                                            <td class="p-1">
                                                @if ($manual)
                                                    <x-ui.input wire:model.blur="{{ $key }}.quantity" inputmode="decimal" class="{{ $cell }} w-24 text-end tabular-nums" :aria-label="__('Quantity')" />
                                                @else
                                                    <span class="block w-24 py-2 text-end tabular-nums">{{ $qty($live['quantity']) }}</span>
                                                @endif
                                            </td>
                                            <td class="p-1"><x-ui.input wire:model.blur="{{ $key }}.rate" inputmode="decimal" class="{{ $cell }} w-24 text-end tabular-nums" :aria-label="__('Rate')" /></td>
                                            <td @class(['p-1 py-2 text-end tabular-nums whitespace-nowrap', 'text-destructive' => $line['deduction'] ?? false])>{{ $money($live['amount']) }}</td>
                                            <td class="p-1">
                                                <x-ui.select native wire:model.blur="{{ $key }}.cost_category_id" class="{{ $cell }} w-28" :aria-label="__('Cost category')">
                                                    <option value="">—</option>
                                                    @foreach ($costCategories as $category)
                                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                    @endforeach
                                                </x-ui.select>
                                            </td>
                                            <td class="p-1"><x-ui.input wire:model.blur="{{ $key }}.remarks" class="{{ $cell }} w-32" :aria-label="__('Remarks')" /></td>
                                            <td class="p-1 whitespace-nowrap">
                                                <x-ui.button type="button" variant="ghost" size="icon" class="size-8" wire:click="duplicateLine({{ $s }}, {{ $l }})" :aria-label="__('Duplicate line')"><x-lucide-copy /></x-ui.button>
                                                <x-ui.button type="button" variant="ghost" size="icon" class="size-8" wire:click="removeLine({{ $s }}, {{ $l }})" :aria-label="__('Remove line')"><x-lucide-x /></x-ui.button>
                                            </td>
                                        </tr>
                                        @if ($lineErrors->isNotEmpty())
                                            <tr wire:key="line-errors-{{ $s }}-{{ $l }}"><td colspan="18" class="px-2 pb-2 text-sm text-destructive">{{ $lineErrors->implode(' ') }}</td></tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Mobile line cards --}}
                        <x-ui.item-group class="gap-2 md:hidden">
                            @foreach ($section['lines'] as $l => $line)
                                @php
                                    $live = $preview['lines']["{$s}.{$l}"] ?? ['quantity' => null, 'amount' => null];
                                    $factors = collect(['nos', 'length', 'width', 'height'])->map(fn ($field) => $line[$field] ?? '')->filter(fn ($value) => $value !== '')->implode(' × ');
                                    $hasError = collect($errors->keys())->contains(fn ($field) => str_starts_with($field, "sections.{$s}.lines.{$l}."));
                                @endphp
                                <x-ui.item variant="outline" @class(['min-h-16 cursor-pointer py-2 active:bg-accent', 'border-destructive' => $hasError]) wire:key="m-line-{{ $s }}-{{ $l }}" wire:click="editLine({{ $s }}, {{ $l }})">
                                    <x-ui.item-content class="min-w-0">
                                        <x-ui.item-title class="text-base"><span class="truncate">{{ $line['description'] !== '' ? $line['description'] : __('New line') }}</span></x-ui.item-title>
                                        <x-ui.item-description class="text-sm tabular-nums">{{ $line['line_no'] !== '' ? $line['line_no'].' · ' : '' }}{{ $factors !== '' && ($line['measurement_formula'] ?? '') !== 'manual' ? $factors.' = ' : '' }}{{ $qty($live['quantity']) }} {{ $unitSymbol($line['unit_id']) }}</x-ui.item-description>
                                    </x-ui.item-content>
                                    <span @class(['shrink-0 text-sm tabular-nums', 'text-destructive' => $line['deduction'] ?? false])>{{ $money($live['amount']) }}</span>
                                    <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                                </x-ui.item>
                            @endforeach
                        </x-ui.item-group>

                        <div class="flex gap-2">
                            <x-ui.button type="button" variant="outline" class="hidden h-9 md:inline-flex" wire:click="addLine({{ $s }})"><x-lucide-plus /> {{ __('Add line') }}</x-ui.button>
                            <x-ui.button type="button" variant="outline" class="h-11 flex-1 md:hidden" wire:click="addLineOnMobile({{ $s }})"><x-lucide-plus /> {{ __('Add line') }}</x-ui.button>
                        </div>
                    </div>
                @endforeach

                <div class="flex flex-wrap gap-2 border-t pt-4">
                    <x-ui.button type="button" variant="outline" class="h-11 md:h-9" wire:click="addSection"><x-lucide-list-plus /> {{ __('Add section') }}</x-ui.button>
                    <x-ui.button type="button" variant="outline" class="h-11 md:h-9" x-on:click="$dispatch('open-sheet-estimate-copy')"><x-lucide-copy /> {{ __('Copy lines from…') }}</x-ui.button>
                    <x-ui.button type="button" variant="outline" class="h-11 md:h-9" x-on:click="$dispatch('open-sheet-estimate-import')"><x-lucide-upload /> {{ __('Import Excel') }}</x-ui.button>
                </div>
                <p class="hidden text-sm text-muted-foreground md:block">{{ __('Enter adds a line, Ctrl+D duplicates, Ctrl+↑/↓ moves.') }}</p>
            @else
                <div class="flex flex-col gap-3">
                    @foreach ($materialLines as $m => $line)
                        @php($live = $preview['materials'][$m] ?? ['total_qty' => null, 'amount' => null])
                        <x-ui.item variant="outline" class="flex-col items-stretch gap-3" wire:key="material-{{ $m }}">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto]">
                                <x-ui.select native wire:model.live="materialLines.{{ $m }}.material_id" class="{{ $input }}" :aria-label="__('Material')">
                                    <option value="">{{ __('Type a name instead…') }}</option>
                                    @foreach ($materials as $material)
                                        <option value="{{ $material->id }}">{{ $material->name }}</option>
                                    @endforeach
                                </x-ui.select>
                                @if (! $line['material_id'])
                                    <x-ui.input wire:model.blur="materialLines.{{ $m }}.material_name" :placeholder="__('Material name, e.g. 16mm Rebar')" class="{{ $input }}" :aria-label="__('Material name')" />
                                @else
                                    <span></span>
                                @endif
                                <x-ui.button type="button" variant="ghost" size="icon" class="size-11 justify-self-end md:size-9" wire:click="removeMaterial({{ $m }})" :aria-label="__('Remove material')"><x-lucide-trash-2 /></x-ui.button>
                            </div>
                            <div class="grid grid-cols-2 gap-3 md:grid-cols-6">
                                <x-ui.field>
                                    <x-ui.field-label class="text-sm">{{ __('Unit') }}</x-ui.field-label>
                                    <x-ui.select native wire:model.blur="materialLines.{{ $m }}.unit_id" class="{{ $input }}">
                                        <option value="">—</option>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->symbol }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.field-label class="text-sm">{{ __('Est. qty') }}</x-ui.field-label>
                                    <x-ui.input wire:model.blur="materialLines.{{ $m }}.estimated_qty" inputmode="decimal" class="{{ $input }} text-end tabular-nums" />
                                </x-ui.field>
                                <x-ui.field>
                                    <x-ui.field-label class="text-sm">{{ __('Wastage %') }}</x-ui.field-label>
                                    <x-ui.input wire:model.blur="materialLines.{{ $m }}.wastage_pct" inputmode="decimal" class="{{ $input }} text-end tabular-nums" />
                                </x-ui.field>
                                <div class="flex flex-col gap-1">
                                    <span class="text-sm font-medium">{{ __('Total qty') }}</span>
                                    <span class="flex h-11 items-center justify-end text-base tabular-nums md:h-9 md:text-sm">{{ $qty($live['total_qty']) }}</span>
                                </div>
                                <x-ui.field>
                                    <x-ui.field-label class="text-sm">{{ __('Rate') }}</x-ui.field-label>
                                    <x-ui.input wire:model.blur="materialLines.{{ $m }}.rate" inputmode="decimal" class="{{ $input }} text-end tabular-nums" />
                                </x-ui.field>
                                <div class="flex flex-col gap-1">
                                    <span class="text-sm font-medium">{{ __('Amount') }}</span>
                                    <span class="flex h-11 items-center justify-end text-base tabular-nums md:h-9 md:text-sm">{{ $money($live['amount']) }}</span>
                                </div>
                            </div>
                            <x-ui.input wire:model.blur="materialLines.{{ $m }}.purpose" :placeholder="__('Purpose')" class="{{ $input }}" :aria-label="__('Purpose')" />
                            <x-ui.field-error :messages="collect($errors->getMessages())->filter(fn ($messages, $field) => str_starts_with($field, 'material_lines.'.$m.'.'))->flatten()->all()" />
                        </x-ui.item>
                    @endforeach
                    <x-ui.button type="button" variant="outline" class="h-11 self-start md:h-9" wire:click="addMaterial"><x-lucide-plus /> {{ __('Add material') }}</x-ui.button>
                    @if ($hasWork)
                        <p class="text-sm text-muted-foreground">{{ __('On a work estimate the materials are a statement; they do not add to the total.') }}</p>
                    @endif
                </div>
            @endif
        </x-shell.form-section>

        <x-slot:aside>
            <x-shell.form-section :title="__('Totals')">
                <dl class="flex flex-col gap-2 text-sm">
                    <div class="flex justify-between"><dt>{{ __('Subtotal') }}</dt><dd class="tabular-nums">{{ $money($preview['totals']['subtotal']) }}</dd></div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="flex items-center gap-2">{{ __('Overhead') }} <x-ui.input wire:model.blur="overhead_pct" inputmode="decimal" class="h-11 w-20 text-end text-base tabular-nums md:h-8 md:text-sm" :aria-label="__('Overhead %')" />%</dt>
                        <dd class="tabular-nums">{{ $money($preview['totals']['overhead_amount']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="flex items-center gap-2">{{ __('Profit') }} <x-ui.input wire:model.blur="profit_pct" inputmode="decimal" class="h-11 w-20 text-end text-base tabular-nums md:h-8 md:text-sm" :aria-label="__('Profit %')" />%</dt>
                        <dd class="tabular-nums">{{ $money($preview['totals']['profit_amount']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="flex items-center gap-2">{{ __('VAT') }} <x-ui.input wire:model.blur="vat_pct" inputmode="decimal" class="h-11 w-20 text-end text-base tabular-nums md:h-8 md:text-sm" :aria-label="__('VAT %')" />%</dt>
                        <dd class="tabular-nums">{{ $money($preview['totals']['vat_amount']) }}</dd>
                    </div>
                    <div class="flex justify-between border-t pt-2 text-base font-semibold"><dt>{{ __('Total') }}</dt><dd class="tabular-nums">{{ \App\Support\Money::format($preview['totals']['total_amount']) }}</dd></div>
                </dl>
                <x-ui.field-error :messages="[...$errors->get('overhead_pct'), ...$errors->get('profit_pct'), ...$errors->get('vat_pct')]" />
                @if ($canSubmit)
                    <x-ui.button type="button" variant="secondary" class="h-11 md:h-9" wire:click="saveAndSubmit" wire:loading.attr="disabled"><x-lucide-send /> {{ __('Save and submit') }}</x-ui.button>
                @endif
            </x-shell.form-section>
            <x-shell.form-section :title="__('People')">
                <x-ui.field>
                    <x-ui.field-label for="prepared_by">{{ __('Prepared by') }} *</x-ui.field-label>
                    <x-employee-select id="prepared_by" wire:model="prepared_by" :include="$estimate?->prepared_by" :placeholder="__('Choose…')" />
                    <x-ui.field-error :messages="$errors->get('prepared_by')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="checked_by">{{ __('Checked by') }}</x-ui.field-label>
                    <x-employee-select id="checked_by" wire:model="checked_by" :include="$estimate?->checked_by" :placeholder="__('None')" />
                    <x-ui.field-error :messages="$errors->get('checked_by')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="notes">{{ __('Notes') }}</x-ui.field-label>
                    <x-ui.textarea id="notes" wire:model="notes" rows="3" class="text-base md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('notes')" />
                </x-ui.field>
            </x-shell.form-section>
        </x-slot:aside>
    </x-shell.form-screen>

    {{-- Mobile line sheet --}}
    <x-shell.sheet id="estimate-line" :title="__('Line')" :description="__('Measurements, unit and rate of this line.')">
        @if ($editingLine !== null)
            @php($key = "sections.{$editSection}.lines.{$editLine}")
            @php($live = $preview['lines']["{$editSection}.{$editLine}"] ?? ['quantity' => null, 'amount' => null])
            <x-ui.field>
                <x-ui.field-label for="sheet-work-item">{{ __('Work item') }}</x-ui.field-label>
                <x-ui.select native id="sheet-work-item" wire:model.live="{{ $key }}.work_item_id" class="{{ $input }}">
                    <option value="">—</option>
                    @foreach ($workItems as $item)
                        <option value="{{ $item->id }}">{{ $item->code }} · {{ $item->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="sheet-description">{{ __('Description') }} *</x-ui.field-label>
                <x-ui.textarea id="sheet-description" wire:model.blur="{{ $key }}.description" rows="2" class="text-base" />
                <x-ui.field-error :messages="$errors->get($key.'.description')" />
            </x-ui.field>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.field>
                    <x-ui.field-label for="sheet-line-no">{{ __('Line no.') }}</x-ui.field-label>
                    <x-ui.input id="sheet-line-no" wire:model.blur="{{ $key }}.line_no" class="{{ $input }}" :placeholder="__('auto')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="sheet-level">{{ __('Level') }}</x-ui.field-label>
                    <x-ui.input id="sheet-level" wire:model.blur="{{ $key }}.level" class="{{ $input }}" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="sheet-location">{{ __('Location') }}</x-ui.field-label>
                <x-ui.input id="sheet-location" wire:model.blur="{{ $key }}.location" class="{{ $input }}" />
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="sheet-formula">{{ __('Formula') }}</x-ui.field-label>
                <x-ui.select native id="sheet-formula" wire:model.live="{{ $key }}.measurement_formula" class="{{ $input }}">
                    @foreach ($formulas as $formula)
                        <option value="{{ $formula->value }}">{{ $formula->label() }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            @if (($editingLine['measurement_formula'] ?? '') === 'manual')
                <x-ui.field>
                    <x-ui.field-label for="sheet-quantity">{{ __('Quantity') }} *</x-ui.field-label>
                    <x-ui.input id="sheet-quantity" wire:model.blur="{{ $key }}.quantity" inputmode="decimal" class="{{ $input }} text-end tabular-nums" />
                    <x-ui.field-error :messages="$errors->get($key.'.quantity')" />
                </x-ui.field>
            @else
                <div class="grid grid-cols-4 gap-2">
                    @foreach (['nos' => __('Nos'), 'length' => __('L'), 'width' => __('W'), 'height' => __('H')] as $dimension => $label)
                        <x-ui.field>
                            <x-ui.field-label for="sheet-{{ $dimension }}">{{ $label }}</x-ui.field-label>
                            <x-ui.input id="sheet-{{ $dimension }}" wire:model.blur="{{ $key }}.{{ $dimension }}" inputmode="decimal" class="{{ $input }} text-end tabular-nums" />
                        </x-ui.field>
                    @endforeach
                </div>
                <x-ui.field-error :messages="[...$errors->get($key.'.length'), ...$errors->get($key.'.width'), ...$errors->get($key.'.height'), ...$errors->get($key.'.nos')]" />
            @endif
            <label class="flex min-h-11 items-center gap-3 text-base">
                <x-ui.checkbox wire:model.live="{{ $key }}.deduction" /> {{ __('Deduct (opening)') }}
            </label>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.field>
                    <x-ui.field-label for="sheet-unit">{{ __('Unit') }} *</x-ui.field-label>
                    <x-ui.select native id="sheet-unit" wire:model.blur="{{ $key }}.unit_id" class="{{ $input }}">
                        <option value="">—</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->symbol }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error :messages="$errors->get($key.'.unit_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="sheet-rate">{{ __('Rate') }}</x-ui.field-label>
                    <x-ui.input id="sheet-rate" wire:model.blur="{{ $key }}.rate" inputmode="decimal" class="{{ $input }} text-end tabular-nums" />
                </x-ui.field>
            </div>
            <x-ui.field>
                <x-ui.field-label for="sheet-category">{{ __('Cost category') }}</x-ui.field-label>
                <x-ui.select native id="sheet-category" wire:model.blur="{{ $key }}.cost_category_id" class="{{ $input }}">
                    <option value="">—</option>
                    @foreach ($costCategories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field>
                <x-ui.field-label for="sheet-remarks">{{ __('Remarks') }}</x-ui.field-label>
                <x-ui.input id="sheet-remarks" wire:model.blur="{{ $key }}.remarks" class="{{ $input }}" />
            </x-ui.field>
            <div class="flex justify-between rounded-md bg-muted px-3 py-2 text-base tabular-nums">
                <span>{{ $qty($live['quantity']) }} {{ $unitSymbol($editingLine['unit_id']) }}</span>
                <span class="font-semibold">৳ {{ $money($live['amount']) }}</span>
            </div>
        @endif
        <x-slot:footer>
            @if ($editingLine !== null)
                <x-ui.button type="button" variant="outline" wire:click="removeLine({{ $editSection }}, {{ $editLine }})" x-on:click="$dispatch('close-sheet-estimate-line')"><x-lucide-trash-2 /> {{ __('Remove') }}</x-ui.button>
            @endif
            <x-ui.button type="button" x-on:click="$dispatch('close-sheet-estimate-line')">{{ __('Done') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    <x-shell.sheet id="estimate-copy" :title="__('Copy lines')" :description="__('Append the lines of another estimate to this one.')">
        <x-ui.field>
            <x-ui.field-label for="copy-from">{{ __('Estimate number') }}</x-ui.field-label>
            <x-ui.input id="copy-from" wire:model="copyFrom" class="{{ $input }} font-mono" placeholder="EST-27-0012" />
            <x-ui.field-error :messages="$errors->get('copyFrom')" />
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button type="button" variant="outline" x-on:click="$dispatch('close-sheet-estimate-copy')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="button" wire:click="copyLines">{{ __('Copy lines') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    <x-shell.sheet id="estimate-import" :title="__('Import lines')" :description="__('An Excel file in the layout of the estimate export. The lines are added below the current ones.')">
        <x-ui.field>
            <x-ui.field-label for="import-file">{{ __('File') }}</x-ui.field-label>
            <input type="file" id="import-file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="text-base md:text-sm" />
            <x-ui.field-error :messages="$errors->get('importFile')" />
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button type="button" variant="outline" x-on:click="$dispatch('close-sheet-estimate-import')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="button" wire:click="importLines" wire:loading.attr="disabled">{{ __('Import') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>
</div>
