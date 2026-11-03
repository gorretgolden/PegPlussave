@props(['tone' => 'muted'])
<span {{ $attributes->merge(['class' => 'pill '.$tone]) }}>{{ $slot }}</span>
