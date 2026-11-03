@props(['checks'])
<ul class="checks">
    @foreach ($checks as $check)
        <li class="{{ $check['ok'] ? 'ok' : (($check['soft'] ?? false) ? 'soft' : 'bad') }}">
            <x-icon :name="$check['ok'] ? 'check' : (($check['soft'] ?? false) ? 'alert' : 'x')" />
            <span>{{ $check['label'] }}</span>
            @if (!empty($check['detail']))<em>{{ $check['detail'] }}</em>@endif
        </li>
    @endforeach
</ul>
