@props(['title', 'sub' => null])
<div class="sec">
    <div>
        <h2>{{ $title }}</h2>
        @if ($sub)<div class="sub">{!! $sub !!}</div>@endif
    </div>
    @isset($actions)<div class="sec-r">{{ $actions }}</div>@endisset
</div>
