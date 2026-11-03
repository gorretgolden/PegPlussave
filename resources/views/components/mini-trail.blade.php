@props(['dots'])
<span class="mini-trail">
    @foreach ($dots as $dot)
        <span class="mt {{ $dot['kind'] }}" title="{{ $dot['label'] }}">
            @if ($dot['kind'] === 'done')<x-icon name="check" />@elseif ($dot['kind'] === 'no')<x-icon name="x" />@endif
        </span>
    @endforeach
</span>
