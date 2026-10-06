<div x-on:open-detail-modal.window="$wire.show($event.detail.url); $dispatch('open-dialog-detail-modal')">
    <x-ui.dialog id="detail-modal" x-init="$watch('open', (isOpen) => isOpen || $wire.close())">
        <x-ui.dialog-content stacked :show-close="false" class="flex max-h-[90dvh] flex-col gap-0 p-0 sm:max-w-[calc(100%-4rem)] xl:max-w-6xl">
            <div class="flex items-center gap-1 border-b px-4 py-2">
                <x-ui.dialog-title class="sr-only">{{ __('Details') }}</x-ui.dialog-title>
                @if ($url)
                    <x-ui.button variant="ghost" size="sm" class="ms-auto" :href="$url" wire:navigate>
                        <x-lucide-maximize-2 /> {{ __('Open full page') }}
                    </x-ui.button>
                @endif
                <x-ui.button variant="ghost" size="icon" @class(['ms-auto' => ! $url]) x-on:click="open = false" :aria-label="__('Close')">
                    <x-lucide-x />
                </x-ui.button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-6">
                <div wire:loading.flex wire:target="show" class="flex-col gap-4">
                    <x-ui.skeleton class="h-8 w-64" />
                    <x-ui.skeleton class="h-24 w-full" />
                    <x-ui.skeleton class="h-64 w-full" />
                </div>

                <div wire:loading.remove wire:target="show">
                    @if ($detail)
                        @livewire($detail['component'], $detail['parameters'], key('detail-'.$url))
                    @endif
                </div>
            </div>
        </x-ui.dialog-content>
    </x-ui.dialog>
</div>
