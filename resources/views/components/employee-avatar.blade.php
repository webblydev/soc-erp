{{--
    An employee's photo, or their initials when there is none.
      employee  App\Modules\Hrm\Models\Employee
--}}
@props(['employee'])

<x-ui.avatar {{ $attributes->twMerge('size-10') }}>
    @if ($employee->photo_path)
        <x-ui.avatar-image :src="$employee->photoUrl()" :alt="$employee->full_name" />
    @endif
    <x-ui.avatar-fallback>{{ $employee->initials() }}</x-ui.avatar-fallback>
</x-ui.avatar>
