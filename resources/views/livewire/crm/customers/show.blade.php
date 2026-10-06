@php
    $user = auth()->user();
    $tabs = ['overview' => __('Overview'), 'activities' => __('Activities'), 'leads' => __('Leads'), 'documents' => __('Documents'), 'notes' => __('Notes'), 'history' => __('History')];
    $logEvent = "\$dispatch('crm-log-activity', { subjectType: 'customer', subjectId: {$customer->id}, mode: 'log' })";
@endphp

<x-slot:actions>
    <x-ui.button variant="ghost" size="icon" class="size-11" x-on:click="$dispatch('open-sheet-customer-actions')" :aria-label="__('Customer actions')">
        <x-lucide-ellipsis-vertical class="size-5" />
    </x-ui.button>
</x-slot:actions>

<div class="flex flex-col gap-6 pb-24 md:pb-0">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 flex-col gap-1">
            <span class="font-mono text-sm text-muted-foreground max-md:hidden">{{ $customer->customer_number }}</span>
            <h1 class="truncate text-xl font-semibold tracking-tight md:text-2xl">{{ $customer->name }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-ui.badge :tone="$customer->status->color ?? 'neutral'" class="text-sm">{{ $customer->status->name }}</x-ui.badge>
                <span class="text-muted-foreground">{{ $customer->accountManager?->name ?? __('No account manager') }}</span>
            </div>
        </div>
        <div class="hidden flex-wrap justify-end gap-2 md:flex">
            @can('update', $customer)
                <x-ui.button size="sm" variant="outline" :href="route('crm.customers.edit', $customer)" wire:navigate><x-lucide-pencil /> {{ __('Edit') }}</x-ui.button>
            @endcan
            @can('crm.activities.create')
                <x-ui.button size="sm" x-on:click="{{ $logEvent }}"><x-lucide-notebook-pen /> {{ __('Log activity') }}</x-ui.button>
            @endcan
            @if (Route::has('crm.customers.merge') && $user->can('merge', $customer))
                <x-ui.button size="sm" variant="outline" :href="route('crm.customers.merge', $customer)" wire:navigate><x-lucide-merge /> {{ __('Merge') }}</x-ui.button>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
        <aside class="grid grid-cols-2 gap-3 lg:order-last lg:grid-cols-1">
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Source') }}</span>
                <span class="text-sm font-medium">{{ $customer->leadSource?->name ?? '—' }}</span>
            </x-ui.card>
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Acquired by') }}</span>
                <span class="text-sm font-medium">{{ $customer->acquiredBy?->name ?? '—' }}</span>
            </x-ui.card>
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('First lead') }}</span>
                @if ($customer->sourceLead)
                    <a href="{{ route('crm.leads.show', $customer->sourceLead) }}" wire:navigate class="font-mono text-sm font-medium hover:underline">{{ $customer->sourceLead->lead_number }}</a>
                @else
                    <span class="text-sm font-medium">—</span>
                @endif
            </x-ui.card>
            <x-ui.card class="gap-1 p-4">
                <span class="text-sm text-muted-foreground">{{ __('Customer since') }}</span>
                <span class="text-sm font-medium tabular-nums">{{ $customer->created_at->format('d-M-Y') }}</span>
            </x-ui.card>
            <x-ui.card class="col-span-2 gap-1 p-4 lg:col-span-1">
                <span class="text-sm text-muted-foreground">{{ __('Credit limit') }}</span>
                <span class="text-sm font-medium tabular-nums">{{ $customer->credit_limit !== null ? \App\Support\Money::format($customer->credit_limit) : __('No limit') }}</span>
            </x-ui.card>
        </aside>

        <div class="flex min-w-0 flex-col gap-4 lg:col-span-2">
            <div class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                <x-ui.segmented-control name="customer-tab" wire:model.live="tab" :value="$tab" class="h-11 md:h-9"
                    :options="collect($tabs)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->all()" />
            </div>

            @if ($tab === 'overview')
                <x-ui.card class="p-4 md:p-6">
                    <h2 class="text-base font-semibold">{{ __('Identity') }}</h2>
                    <x-ui.description-list>
                        <x-ui.description-item :term="__('Type')">{{ $customer->type->name }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Company')">{{ $customer->company_name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('NID / registration no.')">{{ $customer->nid_or_reg_no ?? '—' }}</x-ui.description-item>
                    </x-ui.description-list>

                    <h2 class="mt-4 text-base font-semibold">{{ __('Contact') }}</h2>
                    <x-ui.description-list>
                        <x-ui.description-item :term="__('Phone')"><a href="tel:{{ $customer->phone }}" class="tabular-nums hover:underline">{{ $customer->phone }}</a></x-ui.description-item>
                        <x-ui.description-item :term="__('Alternate phone')">{{ $customer->alternate_phone ?? '—' }}</x-ui.description-item>
                        @if ($customer->whatsapp)
                            <x-ui.description-item :term="__('WhatsApp')"><a href="https://wa.me/88{{ $customer->whatsapp }}" target="_blank" rel="noopener" class="tabular-nums hover:underline">{{ $customer->whatsapp }}</a></x-ui.description-item>
                        @endif
                        <x-ui.description-item :term="__('Email')">{{ $customer->email ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Address')">{{ $customer->address ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Location')">{{ $customer->location?->full_path ?? '—' }}</x-ui.description-item>
                    </x-ui.description-list>

                    <h2 class="mt-4 text-base font-semibold">{{ __('Classification') }}</h2>
                    <x-ui.description-list>
                        <x-ui.description-item :term="__('Business line')">{{ $customer->businessLine?->name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Account manager')">{{ $customer->accountManager?->name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('Also a vendor')">{{ $customer->is_also_vendor ? __('Yes') : __('No') }}</x-ui.description-item>
                    </x-ui.description-list>

                    <h2 class="mt-4 text-base font-semibold">{{ __('Finance') }}</h2>
                    <x-ui.description-list>
                        <x-ui.description-item :term="__('Payment term')">{{ $customer->paymentTerm?->name ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('TIN')">{{ $customer->tin ?? '—' }}</x-ui.description-item>
                        <x-ui.description-item :term="__('BIN')">{{ $customer->bin ?? '—' }}</x-ui.description-item>
                    </x-ui.description-list>

                    <h2 class="mt-4 text-base font-semibold">{{ __('Contacts') }}</h2>
                    <x-ui.item-group class="gap-2">
                        @forelse ($customer->contacts as $contact)
                            <x-ui.item variant="outline" wire:key="contact-{{ $contact->id }}">
                                <x-ui.item-content>
                                    <x-ui.item-title class="flex items-center gap-2 text-sm">
                                        {{ $contact->name }}
                                        @if ($contact->is_primary)<x-ui.badge tone="info" class="text-sm">{{ __('Primary') }}</x-ui.badge>@endif
                                    </x-ui.item-title>
                                    <x-ui.item-description class="text-sm">{{ collect([$contact->designation, $contact->phone, $contact->email])->filter()->implode(' · ') }}</x-ui.item-description>
                                </x-ui.item-content>
                                @if ($contact->phone)
                                    <x-ui.button variant="ghost" size="icon" class="size-11 md:size-9" href="tel:{{ $contact->phone }}" :aria-label="__('Call :name', ['name' => $contact->name])"><x-lucide-phone /></x-ui.button>
                                @endif
                            </x-ui.item>
                        @empty
                            <p class="text-sm text-muted-foreground">{{ __('No contacts yet.') }}</p>
                        @endforelse
                    </x-ui.item-group>

                    @if ($customer->notes)
                        <h2 class="mt-4 text-base font-semibold">{{ __('Notes') }}</h2>
                        <p class="whitespace-pre-line text-sm">{{ $customer->notes }}</p>
                    @endif
                </x-ui.card>
            @elseif ($tab === 'activities')
                @can('crm.activities.create')
                    <x-ui.button class="h-11 self-start md:h-9" x-on:click="{{ $logEvent }}"><x-lucide-plus /> {{ __('Log activity') }}</x-ui.button>
                @endcan
                @include('livewire.crm.activities.partials.timeline', ['activities' => $activities, 'showSubject' => true])
            @elseif ($tab === 'leads')
                <x-ui.item-group class="gap-2">
                    @forelse ($leads as $lead)
                        <x-ui.item variant="outline" class="min-h-16 active:bg-accent" :href="route('crm.leads.show', $lead)" wire:navigate wire:key="lead-{{ $lead->id }}">
                            <x-ui.item-content class="min-w-0">
                                <x-ui.item-title class="flex items-center gap-2 text-base md:text-sm">
                                    <span class="truncate">{{ $lead->name }}</span>
                                    @if ($lead->id === $customer->source_lead_id)
                                        <x-ui.badge tone="info" class="text-sm">{{ __('Origin') }}</x-ui.badge>
                                    @endif
                                </x-ui.item-title>
                                <x-ui.item-description class="text-sm"><span class="font-mono">{{ $lead->lead_number }}</span> · {{ $lead->lead_date->format('d-M-Y') }}</x-ui.item-description>
                            </x-ui.item-content>
                            <x-ui.badge :tone="$lead->status->color ?? 'info'" class="shrink-0 text-sm">{{ $lead->status->name }}</x-ui.badge>
                            <x-lucide-chevron-right class="size-4 shrink-0 text-muted-foreground" />
                        </x-ui.item>
                    @empty
                        <p class="py-8 text-center text-sm text-muted-foreground">{{ __('No leads yet.') }}</p>
                    @endforelse
                </x-ui.item-group>
            @elseif ($tab === 'documents')
                <livewire:foundation.attachments :model="$customer" :key="'attachments-'.$customer->id" />
            @elseif ($tab === 'notes')
                <livewire:foundation.notes :model="$customer" :key="'notes-'.$customer->id" />
            @else
                <livewire:foundation.history :model="$customer" :key="'history-'.$customer->id" />
            @endif
        </div>
    </div>

    <x-shell.sheet id="customer-actions" :title="__('Customer actions')">
        <div class="flex flex-col gap-2 pb-4">
            @can('update', $customer)
                <x-ui.button class="h-11 justify-start" variant="outline" :href="route('crm.customers.edit', $customer)" wire:navigate><x-lucide-pencil /> {{ __('Edit') }}</x-ui.button>
            @endcan
            @can('crm.activities.create')
                <x-ui.button class="h-11 justify-start" variant="outline" x-on:click="$dispatch('close-sheet-customer-actions'); {{ $logEvent }}"><x-lucide-notebook-pen /> {{ __('Log activity') }}</x-ui.button>
            @endcan
            @if (Route::has('crm.customers.merge') && $user->can('merge', $customer))
                <x-ui.button class="h-11 justify-start" variant="outline" :href="route('crm.customers.merge', $customer)" wire:navigate><x-lucide-merge /> {{ __('Merge') }}</x-ui.button>
            @endif
        </div>
    </x-shell.sheet>

    <x-shell.sheet id="activity-delete" :title="__('Delete activity?')">
        <x-slot:footer>
            <x-ui.button variant="outline" x-on:click="$dispatch('close-sheet-activity-delete')">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button variant="destructive" wire:click="deleteActivity">{{ __('Delete') }}</x-ui.button>
        </x-slot:footer>
    </x-shell.sheet>

    <livewire:crm.quick-log />
</div>
