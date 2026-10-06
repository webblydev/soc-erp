{{--
    Upload → preview → result screen for an import (spec C10). Used inside a Livewire component
    with the WithImport trait; all actions are wire: calls on that component.
--}}
@props(['step', 'columns', 'rows' => [], 'counts' => [], 'shown' => 0, 'result' => [], 'notes' => [], 'errorsOnly' => false, 'backUrl', 'hasFailedFile' => false])

@php($importable = ($counts['new'] ?? 0) + ($counts['update'] ?? 0))

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 {{ $step === 'preview' ? 'pb-28 md:pb-0' : '' }}">
    <ol class="flex items-center gap-2 text-sm" aria-label="{{ __('Import steps') }}">
        @foreach (['upload' => __('Upload'), 'preview' => __('Preview'), 'result' => __('Result')] as $key => $label)
            <li class="flex items-center gap-2">
                <x-ui.badge :variant="$key === $step ? 'default' : 'secondary'">{{ $loop->iteration }}</x-ui.badge>
                <span @class(['font-medium' => $key === $step, 'text-muted-foreground' => $key !== $step])>{{ $label }}</span>
                @unless ($loop->last)
                    <x-lucide-chevron-right class="size-4 text-muted-foreground" />
                @endunless
            </li>
        @endforeach
    </ol>

    @if ($step === 'upload')
        <x-ui.card>
            <x-ui.card-content class="flex flex-col gap-6">
                <ul class="flex list-disc flex-col gap-1 ps-5 text-sm text-muted-foreground">
                    @foreach ($notes as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>

                <div class="flex flex-col gap-1 text-sm">
                    <span class="font-medium">{{ __('Columns') }}</span>
                    <span class="font-mono text-muted-foreground">{{ implode(', ', array_keys($columns)) }}</span>
                </div>

                <x-ui.button variant="outline" class="h-11 self-start md:h-9" wire:click="downloadTemplate">
                    <x-lucide-download /> {{ __('Download template') }}
                </x-ui.button>

                <x-ui.field>
                    <x-ui.field-label for="import-file">{{ __('File (.xlsx or .csv, up to 5 MB)') }}</x-ui.field-label>
                    <x-ui.input id="import-file" type="file" wire:model="file" accept=".xlsx,.csv" class="h-11 text-base md:h-9 md:text-sm" />
                    <div wire:loading wire:target="file" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <x-ui.spinner class="size-4" /> {{ __('Reading the file…') }}
                    </div>
                    <x-ui.field-error :messages="$errors->get('file')" />
                </x-ui.field>
            </x-ui.card-content>
        </x-ui.card>
    @elseif ($step === 'preview')
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge tone="success" class="text-sm">{{ __('New :count', ['count' => $counts['new'] ?? 0]) }}</x-ui.badge>
            <x-ui.badge tone="info" class="text-sm">{{ __('Update :count', ['count' => $counts['update'] ?? 0]) }}</x-ui.badge>
            <x-ui.badge tone="danger" class="text-sm">{{ __('Error :count', ['count' => $counts['error'] ?? 0]) }}</x-ui.badge>

            <x-ui.field orientation="horizontal" class="ms-auto min-h-11 w-auto items-center">
                <x-ui.switch id="errors-only" wire:model.live="errorsOnly" :checked="$errorsOnly" />
                <x-ui.field-label for="errors-only">{{ __('Errors only') }}</x-ui.field-label>
            </x-ui.field>

            <div class="hidden gap-2 md:flex">
                <x-ui.button variant="outline" wire:click="startOver">{{ __('Start over') }}</x-ui.button>
                <x-ui.button wire:click="confirm" :disabled="$importable === 0" wire:loading.attr="disabled" wire:target="confirm">
                    <x-lucide-loader-circle class="animate-spin" wire:loading wire:target="confirm" />
                    {{ __('Import :count rows', ['count' => $importable]) }}
                </x-ui.button>
            </div>
        </div>

        <div class="hidden md:block">
            <x-ui.table>
                <x-ui.table-header>
                    <x-ui.table-row>
                        <x-ui.table-head>{{ __('Row') }}</x-ui.table-head>
                        @foreach (array_keys($columns) as $heading)
                            <x-ui.table-head>{{ $heading }}</x-ui.table-head>
                        @endforeach
                        <x-ui.table-head>{{ __('Status') }}</x-ui.table-head>
                        <x-ui.table-head>{{ __('Errors') }}</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @foreach ($rows as $row)
                        <x-ui.table-row wire:key="import-row-{{ $row->number }}">
                            <x-ui.table-cell class="tabular-nums text-muted-foreground">{{ $row->number }}</x-ui.table-cell>
                            @foreach (array_keys($columns) as $heading)
                                <x-ui.table-cell @class(['font-mono' => $heading === 'code'])>{{ $row->values[$heading] ?? '' }}</x-ui.table-cell>
                            @endforeach
                            <x-ui.table-cell><x-ui.badge :tone="$row->status->tone()">{{ $row->status->label() }}</x-ui.badge></x-ui.table-cell>
                            <x-ui.table-cell class="text-sm text-destructive">{{ implode(' ', $row->errors) }}</x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table-body>
            </x-ui.table>
        </div>

        <x-ui.item-group class="gap-2 md:hidden">
            @foreach ($rows as $row)
                <x-ui.item variant="outline" class="min-h-16 flex-col items-stretch gap-1 py-2" wire:key="m-import-row-{{ $row->number }}">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-muted-foreground">{{ __('Row :n', ['n' => $row->number]) }}</span>
                        <span class="font-mono text-sm">{{ $row->values['code'] ?? '' }}</span>
                        <x-ui.badge :tone="$row->status->tone()" class="ms-auto text-sm">{{ $row->status->label() }}</x-ui.badge>
                    </div>
                    <span class="text-base">{{ $row->values['name'] ?? '' }}</span>
                    @foreach ($row->errors as $message)
                        <span class="text-sm text-destructive">{{ $message }}</span>
                    @endforeach
                </x-ui.item>
            @endforeach
        </x-ui.item-group>

        @if ($shown > count($rows))
            <x-ui.button variant="outline" class="h-11 self-center md:h-9" wire:click="showMore">
                {{ __('Show more (:count of :total)', ['count' => count($rows), 'total' => $shown]) }}
            </x-ui.button>
        @endif

        <div class="fixed inset-x-0 bottom-0 z-40 flex gap-2 border-t bg-background px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] md:hidden">
            <x-ui.button variant="outline" class="h-11 flex-1" wire:click="startOver">{{ __('Start over') }}</x-ui.button>
            <x-ui.button class="h-11 flex-1" wire:click="confirm" :disabled="$importable === 0">{{ __('Import :count', ['count' => $importable]) }}</x-ui.button>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-3">
            @foreach (['created' => __('Created'), 'updated' => __('Updated'), 'failed' => __('Failed')] as $key => $label)
                <x-ui.card>
                    <x-ui.card-content class="flex flex-col gap-1">
                        <span class="text-sm text-muted-foreground">{{ $label }}</span>
                        <span @class(['text-3xl font-semibold tabular-nums', 'text-destructive' => $key === 'failed' && ($result[$key] ?? 0) > 0])>{{ $result[$key] ?? 0 }}</span>
                    </x-ui.card-content>
                </x-ui.card>
            @endforeach
        </div>

        <div class="flex flex-col gap-2 md:flex-row">
            @if ($hasFailedFile)
                <x-ui.button variant="outline" class="h-11 md:h-9" wire:click="downloadFailed"><x-lucide-download /> {{ __('Download failed rows') }}</x-ui.button>
            @endif
            <x-ui.button variant="outline" class="h-11 md:h-9" wire:click="startOver">{{ __('Import another file') }}</x-ui.button>
            <x-ui.button class="h-11 md:h-9" :href="$backUrl" wire:navigate>{{ __('Back to list') }}</x-ui.button>
        </div>
    @endif
</div>
