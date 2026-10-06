@php($impersonator = \App\Models\User::query()->find(session(\App\Http\Middleware\HandleImpersonation::SESSION_KEY)))

@if ($impersonator)
    <div data-test="impersonation-banner" role="status" class="flex flex-wrap items-center gap-2 bg-warning px-4 py-2 text-sm text-warning-foreground">
        <x-lucide-venetian-mask class="size-4 shrink-0" />
        <span class="flex-1">{{ __('Signed in as :name', ['name' => auth()->user()->name]) }}</span>
        <form method="POST" action="{{ route('impersonation.stop') }}">
            @csrf
            <x-ui.button type="submit" size="sm" variant="outline" class="h-11 bg-background md:h-8">{{ __('Return to :name', ['name' => $impersonator->name]) }}</x-ui.button>
        </form>
    </div>
@endif
