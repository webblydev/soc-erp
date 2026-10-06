<div>
    <x-shell.form-section :title="__('Notes')" :description="__('Quick notes for the team. Pinned notes stay on top.')">
        <h2 class="text-base font-semibold md:hidden">{{ __('Notes') }}</h2>

        @can('notes.create')
            <form wire:submit="add" class="flex flex-col gap-2">
                <x-ui.textarea wire:model="body" rows="3" maxlength="5000" :placeholder="__('Write a note…')" :aria-label="__('Note')" class="text-base md:text-sm" :aria-invalid="$errors->has('body') ? 'true' : null" />
                <x-ui.field-error :messages="$errors->get('body')" />
                <x-ui.button type="submit" class="h-11 self-end md:h-9" wire:loading.attr="disabled" wire:target="add">{{ __('Add note') }}</x-ui.button>
            </form>
        @endcan

        @if ($notes->isEmpty())
            <p class="py-6 text-center text-sm text-muted-foreground">{{ __('No notes yet.') }}</p>
        @else
            <ul class="flex flex-col gap-2">
                @foreach ($notes as $note)
                    <li wire:key="note-{{ $note->id }}" @class(['flex gap-2 rounded-md border p-3', 'border-primary/40 bg-primary/5' => $note->is_pinned])>
                        <div class="min-w-0 flex-1">
                            <p class="whitespace-pre-line break-words text-base md:text-sm">{{ $note->body }}</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ $note->author?->name }} · {{ $note->created_at->diffForHumans() }}
                                @if ($note->is_pinned)
                                    · <span class="font-medium text-primary">{{ __('Pinned') }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-start gap-1">
                            @can('notes.create')
                                <x-ui.button variant="ghost" size="icon" class="size-11 md:size-8" wire:click="togglePin({{ $note->id }})" :aria-label="$note->is_pinned ? __('Unpin') : __('Pin')" :aria-pressed="$note->is_pinned ? 'true' : 'false'">
                                    <x-dynamic-component :component="$note->is_pinned ? 'lucide-pin-off' : 'lucide-pin'" />
                                </x-ui.button>
                            @endcan
                            @if ($canDelete($note))
                                <x-ui.button variant="ghost" size="icon" class="size-11 md:size-8" wire:click="confirmDelete({{ $note->id }})" :aria-label="__('Delete note')"><x-lucide-trash-2 /></x-ui.button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-shell.form-section>

    <x-shell.sheet id="note-delete" :title="__('Delete this note?')" :description="__('It disappears for everyone.')">
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-note-delete')">{{ __('Keep') }}</x-ui.button>
            @if ($deletingId)
                <x-ui.button variant="destructive" wire:click="delete({{ $deletingId }})">{{ __('Delete') }}</x-ui.button>
            @endif
        </x-slot:footer>
    </x-shell.sheet>
</div>
