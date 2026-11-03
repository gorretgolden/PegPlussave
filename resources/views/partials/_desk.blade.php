{{-- What is waiting on this office. A number means something the person can clear; the clock is a
     standing fact of the desk, such as the next meeting or the balance of the box. --}}
@if ($desk !== [])
    <section class="panel">
        <x-sec :title="'Your desk · '.auth()->user()->roleLabel()" />
        <div class="desk">
            @foreach ($desk as $row)
                <div class="desk-row">
                    <span class="desk-n {{ $row['n'] === 0 ? 'zero' : '' }}">
                        @if ($row['n'] === null)
                            <x-icon name="clock" />
                        @else
                            {{ $row['n'] }}
                        @endif
                    </span>
                    <span class="desk-l">{{ $row['label'] }}</span>
                    @if ($row['btn'] && $row['href'])
                        <a class="btn sm" href="{{ $row['href'] }}">{{ $row['btn'] }}</a>
                    @elseif ($row['btn'])
                        <button class="btn sm" data-open="{{ $row['modal'] }}">{{ $row['btn'] }}</button>
                    @else
                        <span></span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif
