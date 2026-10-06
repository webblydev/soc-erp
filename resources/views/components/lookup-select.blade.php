{{--
    A native select over a registered lookup table (docs/01 §5.14): active options in sort order,
    plus the ids the record already holds so an inactive value still shows (CM-BR-03).
      table        lookup table from config/lookups.php
      include      id or ids to keep even when inactive (usually the bound value)
      placeholder  label of the empty first option; null leaves it out
      showCode     prefix each label with the row code
--}}
@props([
    'table',
    'include' => null,
    'placeholder' => null,
    'showCode' => false,
])

<x-ui.select native {{ $attributes->twMerge('h-11 text-base md:h-9 md:text-sm') }}>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach (\App\Support\Facades\Lookup::options($table, is_numeric($include) ? (int) $include : (is_array($include) ? $include : null)) as $option)
        <option value="{{ $option->id }}">{{ $showCode ? $option->code.' — '.$option->name : $option->name }}</option>
    @endforeach
</x-ui.select>
