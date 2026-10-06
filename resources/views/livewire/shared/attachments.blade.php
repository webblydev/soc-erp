<div>
    <x-shell.form-section :title="__('Attachments')" :description="__('Files kept with this record. Downloads use links that expire.')">
        @can('attachments.upload')
            <x-slot:action>
                <x-ui.button variant="outline" size="sm" wire:click="startUpload"><x-lucide-upload /> {{ __('Upload') }}</x-ui.button>
            </x-slot:action>
        @endcan

        <div class="flex items-center justify-between md:hidden">
            <h2 class="text-base font-semibold">{{ __('Attachments') }}</h2>
            @can('attachments.upload')
                <x-ui.button variant="outline" class="h-11" wire:click="startUpload"><x-lucide-upload /> {{ __('Upload') }}</x-ui.button>
            @endcan
        </div>

        @if ($attachments->isEmpty())
            <x-ui.empty class="border border-dashed py-8">
                <x-ui.empty-header>
                    <x-ui.empty-media variant="icon"><x-lucide-paperclip /></x-ui.empty-media>
                    <x-ui.empty-title>{{ __('No files yet') }}</x-ui.empty-title>
                    <x-ui.empty-description>{{ __('Uploaded files appear here.') }}</x-ui.empty-description>
                </x-ui.empty-header>
            </x-ui.empty>
        @else
            <x-ui.item-group class="gap-2">
                @foreach ($attachments as $attachment)
                    <x-ui.item variant="outline" class="min-h-14 py-2" wire:key="attachment-{{ $attachment->id }}">
                        <x-ui.item-media variant="icon"><x-lucide-file-text /></x-ui.item-media>
                        <x-ui.item-content class="min-w-0">
                            <x-ui.item-title class="w-full text-base md:text-sm">
                                <a href="{{ $attachment->downloadUrl() }}" class="truncate hover:underline">{{ $attachment->title ?: $attachment->original_name }}</a>
                                @if ($attachment->version > 1)
                                    <x-ui.badge variant="secondary">v{{ $attachment->version }}</x-ui.badge>
                                @endif
                            </x-ui.item-title>
                            <x-ui.item-description class="text-sm">
                                {{ collect([$attachment->documentType?->name, \Illuminate\Support\Number::fileSize($attachment->size_bytes), $attachment->uploader?->name, $attachment->created_at->format(\App\Support\Facades\Settings::get('general.date_format', 'd-M-Y'))])->filter()->implode(' · ') }}
                            </x-ui.item-description>
                        </x-ui.item-content>
                        <x-ui.item-actions>
                            <x-ui.button variant="ghost" size="icon" class="size-11 max-md:hidden md:size-8" :href="$attachment->downloadUrl()" :aria-label="__('Download')"><x-lucide-download /></x-ui.button>
                            <x-ui.button variant="ghost" size="icon" class="size-11 md:size-8" wire:click="showActions({{ $attachment->id }})" :aria-label="__('More actions')"><x-lucide-ellipsis-vertical /></x-ui.button>
                        </x-ui.item-actions>
                    </x-ui.item>
                @endforeach
            </x-ui.item-group>
        @endif
    </x-shell.form-section>

    <x-shell.sheet id="attachment-upload" :title="$replacing ? __('Upload a new version') : __('Upload a file')" :description="$replacing ? ($replacing->title ?: $replacing->original_name) : null">
        <form wire:submit="save" id="attachment-upload-form" class="flex flex-col gap-4 pb-2">
            <x-ui.field>
                <x-ui.field-label for="attachment-file">{{ __('File') }} *</x-ui.field-label>
                <input id="attachment-file" type="file" wire:model="upload" accept="{{ $acceptedExtensions }}"
                    class="block min-h-11 w-full rounded-md border px-3 py-2 text-base file:me-3 file:rounded file:border-0 file:bg-secondary file:px-3 file:py-1 file:text-sm md:text-sm" />
                <div wire:loading wire:target="upload" class="text-sm text-muted-foreground">{{ __('Uploading…') }}</div>
                <x-ui.field-description>{{ __('PDF, images, drawings (DWG/DXF), Excel, Word or ZIP.') }}</x-ui.field-description>
                <x-ui.field-error :messages="$errors->get('upload')" />
            </x-ui.field>

            @unless ($replacing)
                <x-ui.field>
                    <x-ui.field-label for="attachment-type">{{ __('Document type') }}</x-ui.field-label>
                    <x-lookup-select table="document_types" :include="$documentTypeId" :placeholder="__('Not set')" id="attachment-type" wire:model="documentTypeId" />
                    <x-ui.field-error :messages="$errors->get('document_type_id')" />
                </x-ui.field>

                <x-ui.field>
                    <x-ui.field-label for="attachment-title">{{ __('Title') }}</x-ui.field-label>
                    <x-ui.input id="attachment-title" wire:model="title" maxlength="200" class="h-11 text-base md:h-9 md:text-sm" />
                    <x-ui.field-error :messages="$errors->get('title')" />
                </x-ui.field>
            @endunless
        </form>
        <x-slot:footer>
            <x-ui.button type="submit" form="attachment-upload-form" wire:loading.attr="disabled" wire:target="upload,save">{{ __('Upload') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    <x-shell.sheet id="attachment-actions" :title="$selected ? ($selected->title ?: $selected->original_name) : __('File')" :description="$selected ? __('Version :version', ['version' => $selected->version]) : null">
        @if ($selected)
            <div class="flex flex-col gap-2 pb-2">
                <x-ui.button variant="outline" class="h-11 justify-start md:h-9" :href="$selected->downloadUrl()"><x-lucide-download /> {{ __('Download') }}</x-ui.button>
                @can('attachments.upload')
                    <x-ui.button variant="outline" class="h-11 justify-start md:h-9" wire:click="startReplace({{ $selected->id }})"><x-lucide-file-up /> {{ __('Upload a new version') }}</x-ui.button>
                @endcan

                @php($previous = $selected->previousVersions())
                @if ($previous->isNotEmpty())
                    <p class="pt-2 text-sm font-medium">{{ __('Earlier versions') }}</p>
                    @foreach ($previous as $version)
                        <a href="{{ $version->downloadUrl() }}" wire:key="version-{{ $version->id }}" class="flex min-h-11 items-center justify-between gap-2 rounded-md border px-3 text-sm active:bg-accent">
                            <span class="truncate">v{{ $version->version }} · {{ $version->original_name }}</span>
                            <x-lucide-download class="size-4 shrink-0" />
                        </a>
                    @endforeach
                @endif
            </div>
        @endif
        @if ($selected && $canDeleteSelected)
            <x-slot:footer>
                @if ($confirmingDelete)
                    <x-ui.button variant="outline" wire:click="$set('confirmingDelete', false)">{{ __('Keep') }}</x-ui.button>
                    <x-ui.button variant="destructive" wire:click="delete({{ $selected->id }})">{{ __('Delete file') }}</x-ui.button>
                @else
                    <x-ui.button variant="destructive" wire:click="$set('confirmingDelete', true)"><x-lucide-trash-2 /> {{ __('Delete') }}</x-ui.button>
                @endif
            </x-slot:footer>
        @endif
    </x-shell.sheet>
</div>
