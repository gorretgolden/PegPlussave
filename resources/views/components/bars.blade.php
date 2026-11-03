@props(['rows', 'series', 'height' => 180, 'aria' => 'Bar chart'])
@php
    $top = 1.0;
    foreach ($rows as $row) {
        foreach ($series as $s) {
            $top = max($top, (float) ($row[$s['key']] ?? 0));
        }
    }
    $top = \App\Support\Format::niceMax($top);
    $ticks = [0, .25, .5, .75, 1];
@endphp
<div class="chart" role="img" aria-label="{{ $aria }}">
    <div class="cy" style="height:{{ $height }}px">
        @foreach ($ticks as $f)
            <span style="bottom:{{ $f * 100 }}%">{{ \App\Support\Format::compact($top * $f) }}</span>
        @endforeach
    </div>
    <div class="cp" style="height:{{ $height }}px">
        @foreach ($ticks as $f)
            @continue($f === 0)<i class="gl" style="bottom:{{ $f * 100 }}%"></i>
        @endforeach
        <div class="cols">
            @foreach ($rows as $row)
                <div class="col">
                    @foreach ($series as $s)
                        @php $value = (int) ($row[$s['key']] ?? 0); @endphp
                        <div class="bar" style="height:{{ $top > 0 ? $value / $top * 100 : 0 }}%;background:{{ $s['color'] }}"
                             title="{{ $row['label'] }}, {{ $s['name'] ?? '' }}: {{ \App\Support\Format::ugx($value) }}"></div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
    <div class="cx">
        @foreach ($rows as $row)
            <span>{{ $row['label'] }}</span>
        @endforeach
    </div>
</div>
