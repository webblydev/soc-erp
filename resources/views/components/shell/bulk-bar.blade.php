{{--
    Bulk-action bar above a desktop list table, shown while rows are selected (WithBulkActions).
    The slot holds screen-specific bulk controls; Export selected and Delete selected are built in.
--}}
@props(['exportable' => false, 'deletable' => false])

<div x-show="$wire.selected.length > 0" x-cloak data-test="bulk-bar" {{ $attributes->twMerge('mb-3 flex flex-wrap items-center gap-2 rounded-md border bg-muted/50 p-2') }}>
    <span class="text-sm font-medium"><span x-text="$wire.selected.length"></span> {{ __('selected') }}</span>

    {{ $slot }}

    <div class="ms-auto flex items-center gap-2">
        @if ($exportable)
            <x-ui.button size="sm" variant="outline" wire:click="exportSelected">
                <x-lucide-download /> {{ __('Export selected') }}
            </x-ui.button>
        @endif

        @if ($deletable)
            <x-ui.alert-dialog>
                <x-ui.alert-dialog-trigger>
                    <x-ui.button size="sm" variant="destructive">
                        <x-lucide-trash-2 /> {{ __('Delete selected') }}
                    </x-ui.button>
                </x-ui.alert-dialog-trigger>
                <x-ui.alert-dialog-content>
                    <x-ui.alert-dialog-header>
                        <x-ui.alert-dialog-title>{{ __('Delete selected records?') }}</x-ui.alert-dialog-title>
                        <x-ui.alert-dialog-description>
                            <span x-text="$wire.selected.length"></span> {{ __('selected records will be deleted. Records that are still in use are skipped.') }}
                        </x-ui.alert-dialog-description>
                    </x-ui.alert-dialog-header>
                    <x-ui.alert-dialog-footer>
                        <x-ui.alert-dialog-cancel>{{ __('Cancel') }}</x-ui.alert-dialog-cancel>
                        <x-ui.alert-dialog-action class="bg-destructive text-white hover:bg-destructive/90" wire:click="deleteSelected">{{ __('Delete') }}</x-ui.alert-dialog-action>
                    </x-ui.alert-dialog-footer>
                </x-ui.alert-dialog-content>
            </x-ui.alert-dialog>
        @endif

        <x-ui.button size="sm" variant="ghost" x-on:click="$wire.selected = []">{{ __('Clear') }}</x-ui.button>
    </div>
</div>
