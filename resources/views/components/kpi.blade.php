@props(['label', 'value', 'sub' => null, 'tone' => ''])
<div {{ $attributes->merge(['class' => 'kpi '.$tone]) }}>
    <div class="lbl">{{ $label }}</div>
    <div class="val">{!! $value !!}</div>
    @if ($sub)<div class="sub">{!! $sub !!}</div>@endif
</div>
