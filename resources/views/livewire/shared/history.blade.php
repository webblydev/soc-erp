<div>
    <x-shell.form-section :title="__('History')" :description="__('Every change to this record, newest first.')">
        <h2 class="text-base font-semibold md:hidden">{{ __('History') }}</h2>

        @if ($entries->isEmpty())
            <p class="py-6 text-center text-sm text-muted-foreground">{{ __('No history yet.') }}</p>
        @else
            <x-ui.timeline>
                @foreach ($entries as $entry)
                    @php($changes = $entry->fieldChanges())
                    <x-ui.timeline-item wire:key="history-{{ $entry->id }}" :title="ucfirst(str_replace('_', ' ', $entry->event))"
                        :icon="match ($entry->event) { 'created' => 'plus', 'updated' => 'pencil', 'deleted' => 'trash-2', 'restored' => 'rotate-ccw', 'login' => 'log-in', 'logout' => 'log-out', default => null }">
                        <p class="text-sm">{{ $entry->actorLabel() }} · {{ $entry->created_at->format('d-M-Y H:i') }}</p>

                        @if ($changes !== [])
                            <x-ui.collapsible class="mt-1">
                                <x-ui.collapsible-trigger class="flex min-h-11 items-center gap-1 text-sm font-medium text-foreground active:opacity-70 md:min-h-8">
                                    <x-lucide-chevron-right class="size-4 transition-transform" x-bind:class="open && 'rotate-90'" />
                                    {{ trans_choice(':count field changed|:count fields changed', count($changes)) }}
                                </x-ui.collapsible-trigger>
                                <x-ui.collapsible-content>
                                    <div class="mt-1 flex flex-col gap-2">
                                        @foreach ($changes as $field => $change)
                                            <div class="rounded-md border p-2 text-sm">
                                                <p class="font-medium text-foreground">{{ $field }}</p>
                                                <p class="break-all text-destructive line-through">{{ $change['old'] }}</p>
                                                <p class="break-all text-success">{{ $change['new'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </x-ui.collapsible-content>
                            </x-ui.collapsible>
                        @endif
                    </x-ui.timeline-item>
                @endforeach
            </x-ui.timeline>

            @if ($hasMore)
                <div wire:intersect="loadMore" class="flex justify-center py-4">
                    <x-ui.button variant="ghost" class="h-11" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                        <x-lucide-loader-circle class="animate-spin" wire:loading wire:target="loadMore" />
                        {{ __('Load more') }}
                    </x-ui.button>
                </div>
            @endif
        @endif
    </x-shell.form-section>
</div>
