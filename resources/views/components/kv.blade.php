@props(['pairs'])
{{-- A fact sheet: the label above, the figure under it. Values may carry a pill or a coloured
     figure, so they are the one place a view is allowed to pass markup. A third element in a pair
     puts that pair in mono, which is how a number is read differently from a sentence. --}}
<dl class="kv">
    @foreach (array_values(array_filter($pairs)) as $pair)
        <div>
            <dt>{{ $pair[0] }}</dt>
            <dd @if (! empty($pair[2])) class="{{ $pair[2] }}" @endif>{!! $pair[1] !!}</dd>
        </div>
    @endforeach
</dl>
