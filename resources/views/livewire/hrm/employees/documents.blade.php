@php($input = 'h-11 text-base md:h-9 md:text-sm')

<div class="flex flex-col gap-4">
    @if ($canManage)
        <x-ui.button class="h-11 self-start md:h-9" wire:click="create"><x-lucide-plus /> {{ __('Add document') }}</x-ui.button>
    @endif

    <x-ui.item-group class="gap-2">
        @forelse ($documents as $document)
            @php($state = $document->expiryState())
            <x-ui.item variant="outline" class="min-h-16" wire:key="document-{{ $document->id }}">
                <x-ui.item-media variant="icon"><x-lucide-file-text /></x-ui.item-media>
                <x-ui.item-content class="min-w-0">
                    <x-ui.item-title class="flex flex-wrap items-center gap-2 text-sm">
                        {{ $document->type->name }}
                        @if ($document->document_no)
                            <span class="font-mono font-normal text-muted-foreground">{{ $document->document_no }}</span>
                        @endif
                    </x-ui.item-title>
                    <x-ui.item-description class="flex flex-wrap items-center gap-2 text-sm">
                        @if ($document->issue_date)
                            <span class="tabular-nums">{{ __('Issued :date', ['date' => $document->issue_date->format('d-M-Y')]) }}</span>
                        @endif
                        @if ($document->expiry_date)
                            <span class="tabular-nums">{{ __('Expires :date', ['date' => $document->expiry_date->format('d-M-Y')]) }}</span>
                        @endif
                        @if ($state === 'expired')
                            <x-ui.badge tone="danger" class="text-sm">{{ __('Expired') }}</x-ui.badge>
                        @elseif ($state === 'expiring')
                            <x-ui.badge tone="warning" class="text-sm">{{ trans_choice('Expires in :count day|Expires in :count days', (int) today()->diffInDays($document->expiry_date)) }}</x-ui.badge>
                        @elseif ($state === 'valid')
                            <x-ui.badge tone="success" class="text-sm">{{ __('Valid') }}</x-ui.badge>
                        @endif
                    </x-ui.item-description>
                </x-ui.item-content>
                <x-ui.item-actions class="shrink-0">
                    @if ($document->attachment)
                        <x-ui.button variant="ghost" size="icon" class="size-11 md:size-9" :href="$document->attachment->downloadUrl()" :aria-label="__('Download')"><x-lucide-download /></x-ui.button>
                    @endif
                    @if ($canManage)
                        <x-ui.button variant="ghost" size="icon" class="size-11 md:size-9" wire:click="edit({{ $document->id }})" :aria-label="__('Edit document')"><x-lucide-pencil /></x-ui.button>
                        <x-ui.button variant="ghost" size="icon" class="size-11 text-destructive md:size-9" x-on:click="$dispatch('confirm-action', { title: @js(__('Delete this document and its file?')), confirm: () => $wire.delete({{ $document->id }}) })" :aria-label="__('Delete document')"><x-lucide-trash-2 /></x-ui.button>
                    @endif
                </x-ui.item-actions>
            </x-ui.item>
        @empty
            <p class="py-8 text-center text-sm text-muted-foreground">{{ __('No documents yet.') }}</p>
        @endforelse
    </x-ui.item-group>

    @if ($canManage)
        <x-shell.sheet id="employee-document" :title="$editingId ? __('Edit document') : __('Add document')" :description="__('Identity, contract or certificate papers with their expiry date.')">
            <form id="employee-document-form" wire:submit="save" class="flex flex-col gap-4">
                <x-ui.field>
                    <x-ui.field-label for="document-type">{{ __('Document type') }} *</x-ui.field-label>
                    <x-lookup-select table="employee_document_types" :include="$form['employee_document_type_id']" :placeholder="__('Choose…')" id="document-type" wire:model="form.employee_document_type_id" />
                    <x-ui.field-error :messages="$errors->get('form.employee_document_type_id')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="document-no">{{ __('Document no.') }}</x-ui.field-label>
                    <x-ui.input id="document-no" wire:model="form.document_no" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('form.document_no')" />
                </x-ui.field>
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field>
                        <x-ui.field-label for="document-issue">{{ __('Issue date') }}</x-ui.field-label>
                        <x-ui.input id="document-issue" type="date" wire:model="form.issue_date" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('form.issue_date')" />
                    </x-ui.field>
                    <x-ui.field>
                        <x-ui.field-label for="document-expiry">{{ __('Expiry date') }}</x-ui.field-label>
                        <x-ui.input id="document-expiry" type="date" wire:model="form.expiry_date" class="{{ $input }}" />
                        <x-ui.field-error :messages="$errors->get('form.expiry_date')" />
                    </x-ui.field>
                </div>
                <x-ui.field>
                    <x-ui.field-label for="document-file">{{ $editingId ? __('Replace file') : __('File') }}</x-ui.field-label>
                    <x-ui.input id="document-file" type="file" wire:model="file" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('file')" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="document-notes">{{ __('Notes') }}</x-ui.field-label>
                    <x-ui.input id="document-notes" wire:model="form.notes" class="{{ $input }}" />
                    <x-ui.field-error :messages="$errors->get('form.notes')" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="outline" class="h-11 md:h-9" x-on:click="$dispatch('close-sheet-employee-document')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="employee-document-form" class="h-11 md:h-9" wire:loading.attr="disabled" wire:target="save,file">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-shell.sheet>
    @endif
</div>
