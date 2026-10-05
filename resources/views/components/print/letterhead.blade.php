@props(['company'])

{{-- Company letterhead for A4 prints (docs/00 §7.5, FD-AC-08). --}}
<header {{ $attributes->merge(['class' => 'flex items-start gap-4 border-b pb-4']) }}>
    @if ($company?->logo_path)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($company->logo_path) }}" alt="{{ $company->name }}" class="h-16 w-auto object-contain">
    @endif
    <div class="flex flex-1 flex-col gap-0.5 text-sm">
        <p class="text-lg font-semibold">{{ $company?->name }}</p>
        @if ($company?->address)
            <p class="whitespace-pre-line">{{ $company->address }}</p>
        @endif
        <p>{{ collect([$company?->phone, $company?->email, $company?->website])->filter()->implode(' · ') }}</p>
        <p>
            @if ($company?->tin) <span>{{ __('TIN') }}: {{ $company->tin }}</span> @endif
            @if ($company?->bin) <span class="ms-3">{{ __('BIN') }}: {{ $company->bin }}</span> @endif
        </p>
    </div>
</header>
