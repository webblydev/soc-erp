{{--
    A native select over employees in active employment (HR-BR-06), plus the ids the record
    already holds so a former employee still shows.
      include      id or ids to keep even when no longer assignable (usually the bound value)
      placeholder  label of the empty first option; null leaves it out
--}}
@props([
    'include' => null,
    'placeholder' => null,
])

@php
    $include = array_map('intval', array_values(array_filter(\Illuminate\Support\Arr::wrap($include), 'is_numeric')));
    $employees = \App\Modules\Hrm\Models\Employee::query()
        ->where(fn ($query) => $query->where(fn ($query) => $query->assignable())->when($include !== [], fn ($query) => $query->orWhereIn('id', $include)))
        ->orderBy('full_name')
        ->get(['id', 'full_name', 'employee_code']);
@endphp

<x-ui.select native {{ $attributes->twMerge('h-11 text-base md:h-9 md:text-sm') }}>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($employees as $employee)
        <option value="{{ $employee->id }}">{{ $employee->full_name }} — {{ $employee->employee_code }}</option>
    @endforeach
</x-ui.select>
