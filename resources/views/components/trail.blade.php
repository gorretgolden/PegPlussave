@props(['steps'])
<div class="trail">
    @foreach ($steps as $step)
        @if(! $loop->first)<span class="trail-sep"><x-icon name="arrow" /></span>@endif
        @php
            $stamp = $step['who']
                ? $step['who'].($step['at'] ? ' · '.\App\Support\Format::dateTime($step['at']) : '')
                : ($step['at'] ? \App\Support\Format::date($step['at']) : 'Pending');
        @endphp
        <div class="stamp {{ $step['kind'] }}"@if(!empty($step['comment'])) title="{{ $step['comment'] }}"@endif>
            <x-icon :name="$step['kind'] === 'done' ? 'check' : ($step['kind'] === 'no' ? 'x' : 'clock')" />
            <span>
                <b>{{ $step['verb'] }}@if(!empty($step['role'])) · {{ \App\Support\PermissionCatalog::label($step['role']) }}@endif</b>
                <span class="st-who">{{ $stamp }}</span>
                @if(!empty($step['comment']))<span class="st-who muted">&ldquo;{{ $step['comment'] }}&rdquo;</span>@endif
            </span>
        </div>
    @endforeach
</div>
