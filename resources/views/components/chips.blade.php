@props(['options', 'current', 'param' => 'filter'])
<div class="chips">
    @foreach ($options as $value => $text)
        <a class="chip-f {{ $value === $current ? 'on' : '' }}"
           href="{{ request()->fullUrlWithQuery([$param => $value]) }}">{{ $text }}</a>
    @endforeach
</div>
