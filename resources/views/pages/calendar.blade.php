{{-- The year on one screen. The rhythm is drawn from the policy, so what a reader sees here is what
     the committee has actually agreed to do each month; the entries somebody typed sit on top of it
     and are the only ones that can be removed. --}}
@extends('layouts.peg')

@php
    $active = array_keys(array_filter($types));
    $keep = fn (array $shown) => ['year' => $year, 'types' => implode(',', $shown), 'day' => $day];
    $dayEvents = $byDate[$day] ?? [];
    $perMonth = array_fill(1, 12, 0);
    foreach ($byDate as $date => $list) {
        $perMonth[(int) substr($date, 5, 2)] += count($list);
    }
@endphp

@section('content')
    <div class="cal-head">
        <div class="yr">
            <a class="icon-btn" href="{{ route('pages.calendar', ['year' => $year - 1, 'types' => implode(',', $active)]) }}"
               aria-label="Previous year"><x-icon name="arrow" class="flip" /></a>
            <b>{{ $year }}</b>
            <a class="icon-btn" href="{{ route('pages.calendar', ['year' => $year + 1, 'types' => implode(',', $active)]) }}"
               aria-label="Next year"><x-icon name="arrow" /></a>
        </div>
        <div class="chips">
            @foreach (\App\Models\CalendarEvent::TYPES as $key => $label)
                @php $next = $types[$key] ? array_values(array_diff($active, [$key])) : array_merge($active, [$key]); @endphp
                <a class="chip-f t-{{ $key }} {{ $types[$key] ? 'on' : '' }}"
                   href="{{ route('pages.calendar', $keep($next)) }}" aria-pressed="{{ $types[$key] ? 'true' : 'false' }}">
                    <i class="dot"></i>{{ $label }}
                </a>
            @endforeach
        </div>
        @if ($canAdd)
            <button class="btn primary" type="button" data-open="dlg-event"><x-icon name="plus" />Add activity</button>
        @endif
    </div>

    <div class="cal-wrap">
        <div class="year">
            @foreach (range(1, 12) as $month)
                @php
                    $monthStart = \Carbon\CarbonImmutable::create($year, $month, 1);
                    $lead = $monthStart->dayOfWeekIso - 1;
                    $lastDay = $monthStart->daysInMonth;
                @endphp
                <div class="mon">
                    <h4>
                        {{ \App\Support\Format::monthName($monthStart->format('Y-m')) }}
                        <span>{{ $perMonth[$month] }} item{{ $perMonth[$month] === 1 ? '' : 's' }}</span>
                    </h4>
                    <div class="dgrid">
                        @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $heading)
                            <span class="dh">{{ $heading }}</span>
                        @endforeach
                        @for ($blank = 0; $blank < $lead; $blank++)
                            <span></span>
                        @endfor
                        @for ($date = 1; $date <= $lastDay; $date++)
                            @php
                                $iso = $monthStart->format('Y-m-').str_pad((string) $date, 2, '0', STR_PAD_LEFT);
                                $list = $byDate[$iso] ?? [];
                                $weekday = ($lead + $date - 1) % 7;
                                $marks = array_slice(array_values(array_unique(array_column($list, 'type'))), 0, 3);
                            @endphp
                            <a class="day {{ $weekday >= 5 ? 'wk' : '' }} {{ $iso === $today ? 'today' : '' }} {{ $iso === $day ? 'sel' : '' }}"
                               href="{{ route('pages.calendar', $keep($active)) }}"
                               aria-label="{{ \App\Support\Format::date($iso) }}@if ($list), {{ count($list) }} item{{ count($list) === 1 ? '' : 's' }}@endif">
                                {{ $date }}
                                @if ($marks !== [])
                                    <span class="dots">
                                        @foreach ($marks as $mark)
                                            <i class="dot t-{{ $mark }}"></i>
                                        @endforeach
                                    </span>
                                @endif
                            </a>
                        @endfor
                    </div>
                </div>
            @endforeach
        </div>

        <div class="stack">
            <section class="panel">
                <x-sec :title="\App\Support\Format::date($day)">
                    <x-slot:actions>
                        <span class="sub">{{ count($dayEvents) }} item{{ count($dayEvents) === 1 ? '' : 's' }}</span>
                    </x-slot:actions>
                </x-sec>
                @forelse ($dayEvents as $event)
                    @include('partials._event', ['event' => $event, 'canDelete' => $canAdd])
                @empty
                    <div class="muted small">Nothing on this day. Pick a day with a dot.</div>
                @endforelse
            </section>

            <section class="panel">
                <x-sec :title="'PEG year plan '.$year" />
                @forelse ($plan as $event)
                    @include('partials._event', ['event' => $event, 'canDelete' => $canAdd])
                @empty
                    <div class="muted small">No planned activities yet.</div>
                @endforelse
                <p class="muted small" style="margin-top:10px">
                    Every month: committee meeting on the first Friday, contribution deadline on the
                    {{ \App\Support\Format::ordinal((int) \App\Support\Policy::get('contribution_day')) }},
                    interest posting at month end.
                </p>
            </section>
        </div>
    </div>
@endsection

@section('modals')
    @if ($canAdd)
        <div class="modal-back" id="dlg-event" hidden>
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-event-h">
                <div class="mdl-h">
                    <h3 id="dlg-event-h">Add a PEG activity</h3>
                    <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
                </div>
                <div class="mdl-b">
                    <form method="POST" action="{{ route('calendar.store') }}" class="form" data-reset>
                        @csrf
                        <div class="field">
                            <label for="ev-t">Title</label>
                            <input id="ev-t" name="title" required maxlength="120" placeholder="e.g. Financial literacy session"
                                   value="{{ old('title') }}">
                            @error('title')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="grid2">
                            <div class="field">
                                <label for="ev-d">Date</label>
                                <input id="ev-d" name="date" type="date" required value="{{ old('date', $day) }}">
                                @error('date')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label for="ev-y">Type</label>
                                <select id="ev-y" name="type">
                                    @foreach (\App\Models\CalendarEvent::TYPES as $key => $label)
                                        <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label for="ev-n">Notes</label>
                            <input id="ev-n" name="notes" maxlength="400" placeholder="Venue, facilitator, what to bring"
                                   value="{{ old('notes') }}">
                            @error('notes')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn" data-close-modal>Cancel</button>
                            <button class="btn primary" type="submit">Add to calendar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
