@props(['items'])
<div class="legend">
    @foreach ($items as [$color, $text])
        <span><i style="background:{{ $color }}"></i>{{ $text }}</span>
    @endforeach
</div>
