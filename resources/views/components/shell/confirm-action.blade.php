{{--
    The page-wide confirmation for row and button actions, rendered once in the app layout.
    Open it with
        $dispatch('confirm-action', { title, description, confirmLabel, confirm: () => $wire.deleteRecord(5) })
    x-shell.row-action builds this from wire:click plus wire:confirm.
--}}
<div data-test="confirm-action" x-data="{ title: '', description: '', confirmLabel: '', confirm: null }">
    <x-ui.alert-dialog
        x-on:confirm-action.window="
            title = $event.detail.title;
            description = $event.detail.description ?? @js(__('It will no longer appear in lists or searches.'));
            confirmLabel = $event.detail.confirmLabel ?? @js(__('Delete'));
            confirm = $event.detail.confirm;
            open = true
        ">
        <x-shell.confirm-content>
            <x-ui.alert-dialog-header>
                <x-ui.alert-dialog-title x-text="title"></x-ui.alert-dialog-title>
                <x-ui.alert-dialog-description x-text="description"></x-ui.alert-dialog-description>
            </x-ui.alert-dialog-header>
            <x-ui.alert-dialog-footer class="max-md:flex-row max-md:gap-2 [&>*]:h-11 [&>*]:flex-1 md:[&>*]:h-9 md:[&>*]:flex-none">
                <x-ui.alert-dialog-cancel>{{ __('Cancel') }}</x-ui.alert-dialog-cancel>
                <x-ui.alert-dialog-action class="bg-destructive text-white hover:bg-destructive/90" x-on:click="confirm?.()" x-text="confirmLabel"></x-ui.alert-dialog-action>
            </x-ui.alert-dialog-footer>
        </x-shell.confirm-content>
    </x-ui.alert-dialog>
</div>
