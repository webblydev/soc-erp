<div class="flex flex-col gap-4">
    <div role="tablist" class="hidden gap-4 border-b md:flex">
        @foreach ($groups as $tab)
            <button type="button" role="tab" aria-selected="{{ $tab === $group ? 'true' : 'false' }}" wire:click="$set('group', @js($tab))"
                @class(['px-3 py-2 text-sm capitalize', 'border-b-2 border-primary font-medium' => $tab === $group, 'text-muted-foreground' => $tab !== $group])>
                {{ __(ucfirst($tab)) }}
            </button>
        @endforeach
    </div>
    <div class="overflow-x-auto md:hidden">
        <x-ui.segmented-control name="settings-group" wire:model.live="group" :value="$group" size="lg"
            :options="$groups->map(fn ($tab) => ['value' => $tab, 'label' => __(ucfirst($tab))])->all()" />
    </div>

    <x-shell.form-page wire:submit="save" :submit-label="$readOnly ? null : __('Save :group settings', ['group' => __($group)])">
        <fieldset @disabled($readOnly) class="flex flex-col gap-6" wire:key="group-{{ $group }}">
            @foreach ($fields as $setting)
                @php($model = "values.{$group}.{$setting->key}")
                @php($id = "setting-{$setting->key}")
                @if ($setting->type === 'bool')
                    <x-ui.field orientation="horizontal" class="min-h-11 items-center">
                        <x-ui.switch :id="$id" wire:model="{{ $model }}" :checked="(bool) data_get($values, $group.'.'.$setting->key)" />
                        <x-ui.field-label :for="$id">{{ __($setting->label) }}</x-ui.field-label>
                    </x-ui.field>
                @else
                    <x-ui.field>
                        <x-ui.field-label :for="$id">{{ __($setting->label) }}</x-ui.field-label>
                        @if ($setting->type === 'json')
                            <x-ui.textarea :id="$id" wire:model="{{ $model }}" rows="4" class="font-mono text-base md:text-sm" />
                        @elseif (str_starts_with($setting->type, 'fk:'))
                            <x-lookup-select :table="substr($setting->type, 3)" :include="data_get($values, $group.'.'.$setting->key)" :placeholder="__('None')" :id="$id" wire:model="{{ $model }}" />
                        @else
                            <x-ui.input :id="$id" wire:model="{{ $model }}"
                                :type="$setting->type === 'int' ? 'number' : 'text'"
                                :inputmode="match ($setting->type) { 'int' => 'numeric', 'decimal' => 'decimal', default => null }"
                                class="h-11 text-base md:h-9 md:text-sm" />
                        @endif
                        @if ($setting->help)
                            <x-ui.field-description>{{ __($setting->help) }}</x-ui.field-description>
                        @endif
                        <x-ui.field-error :messages="$errors->get($model)" />
                        <x-ui.field-error :messages="collect($errors->get($model.'.*'))->flatten()->all()" />
                    </x-ui.field>
                @endif
            @endforeach
        </fieldset>
    </x-shell.form-page>
</div>
