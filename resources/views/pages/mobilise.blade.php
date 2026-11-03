{{-- Who has saved this month, and who is being brought in. Names and dates only: the amounts behind
     a contribution stay with the treasury team, so this desk can be read in front of a committee
     without anybody's savings being said aloud. --}}
@extends('layouts.peg')

@php
    $active = $position['active'];
    $saved = $position['saved'];
    $pending = $position['pending'];
    $notYet = $position['not_yet'];
    $percent = $active->count() ? (int) round($saved->count() / $active->count() * 100) : 0;
@endphp

@section('content')
    <div class="rules">
        <x-icon name="lock" />
        <span>Mobilisation shows who has saved, never how much. Amounts stay with the treasury team.</span>
    </div>

    <section class="panel stack">
        <x-sec :title="\App\Support\Format::monthName($month).' contributions'">
            <x-slot:actions>
                <span class="sub">
                    @if ($daysLeft >= 0)
                        {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} to the {{ \App\Support\Format::date($deadline) }} deadline
                    @else
                        Deadline passed {{ -$daysLeft }} {{ \Illuminate\Support\Str::plural('day', -$daysLeft) }} ago
                    @endif
                </span>
            </x-slot:actions>
        </x-sec>

        <div class="prog"><i style="width: {{ $percent }}%"></i></div>
        <div class="muted small">
            {{ $saved->count() }} of {{ $active->count() }} members saved ({{ $percent }}%)
            &middot; {{ $pending->count() }} being checked
            &middot; {{ $notYet->count() }} yet to save
        </div>
    </section>

    <div class="cols2">
        <section class="stack">
            <x-sec title="Yet to save" sub="Log each follow-up so the committee sees it" />

            <div class="tbl fit">
                <table>
                    <thead>
                    <tr>
                        <th>Member</th>
                        <th>Ext.</th>
                        <th>Last saved</th>
                        <th>Reminders</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($notYet as $member)
                        @php $logs = $reminders->get($member->id, collect()); @endphp
                        <tr>
                            <td>{{ $member->name }}<span class="cell-sub">{{ $member->department }}</span></td>
                            <td class="mono">{{ $member->extension }}</td>
                            <td>{{ $lastSaved[$member->id] ? \App\Support\Format::month($lastSaved[$member->id]) : 'Never' }}</td>
                            <td>
                                @if ($logs->isNotEmpty())
                                    {{ $logs->count() }} &middot; {{ \App\Support\Format::date($logs->last()->created_at->toDateString()) }}
                                @else
                                    None
                                @endif
                            </td>
                            <td class="row-act">
                                @if ($canRemind)
                                    <form method="POST" action="{{ route('mobilise.remind', $member) }}" class="inline">
                                        @csrf
                                        <button class="btn sm" type="submit">Log reminder</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($notYet->isEmpty())
                        <x-empty-row :cols="5" message="Everyone has saved this month." />
                    @endif
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel stack">
            <x-sec title="Saved or being checked" />
            <div class="name-chips">
                @foreach ($saved as $member)
                    <span class="nchip">{{ $member->name }}</span>
                @endforeach
                @foreach ($pending as $member)
                    <span class="nchip">{{ $member->name }} &middot; checking</span>
                @endforeach
                @if ($saved->isEmpty() && $pending->isEmpty())
                    <span class="muted small">Nobody has been recorded yet this month.</span>
                @endif
            </div>
        </section>
    </div>

    <section class="stack">
        <x-sec title="Membership drive" sub="PlusSave staff who are not yet in PEG">
            @if ($canMobilise)
                <x-slot:actions>
                    <button class="btn sm primary" type="button" data-open="dlg-prospect"><x-icon name="plus" />Add prospect</button>
                </x-slot:actions>
            @endif
        </x-sec>

        <div class="board">
            @foreach ($stages as $column)
                <div class="bcol">
                    <h4>{{ $column['stage'] }}<span>{{ $column['prospects']->count() }}</span></h4>

                    @foreach ($column['prospects'] as $prospect)
                        <div class="bcard">
                            <b>{{ $prospect->name }}</b>
                            <span class="muted small">{{ $prospect->department }}@if ($prospect->note) &middot; {{ $prospect->note }}@endif</span>

                            @if ($canMobilise)
                                <div class="row">
                                    @if ($prospect->stage === 'Approached')
                                        <form method="POST" action="{{ route('mobilise.prospect.move', $prospect) }}" class="inline">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="stage" value="Interested">
                                            <button class="btn sm" type="submit">Mark interested</button>
                                        </form>
                                    @endif

                                    @if ($prospect->stage === 'Interested' && $canRegister)
                                        <a class="btn sm primary" href="{{ route('pages.directory', ['prospect' => $prospect->id, 'status' => 'all']) }}">Register</a>
                                    @endif

                                    @if ($prospect->stage === 'Registered')
                                        @if (in_array($prospect->name, $column['awaiting'], true))
                                            <x-pill tone="warn">Awaiting Chairperson</x-pill>
                                        @else
                                            <x-pill tone="ok">On the register</x-pill>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if ($column['prospects']->isEmpty())
                        <span class="muted small" style="padding: 4px">None</span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endsection

@section('modals')
    @if ($canMobilise)
        <div class="modal-back" id="dlg-prospect" hidden>
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-prospect-h">
                <div class="mdl-h">
                    <h3 id="dlg-prospect-h">Add a prospective member</h3>
                    <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
                </div>
                <div class="mdl-b">
                    <form method="POST" action="{{ route('mobilise.prospect') }}" class="form" data-reset>
                        @csrf
                        <p class="muted small">Somebody who has been asked but has not joined. The drive starts at Approached.</p>
                        <div class="grid2">
                            <div class="field">
                                <label for="pr-n">Name</label>
                                <input id="pr-n" name="name" required maxlength="120" value="{{ old('name') }}">
                                @error('name')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label for="pr-d">Department</label>
                                <select id="pr-d" name="dept">
                                    @foreach ($departments as $option)
                                        <option @selected(old('dept') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('dept')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="field">
                            <label for="pr-o">Note</label>
                            <input id="pr-o" name="note" maxlength="200" placeholder="e.g. Asked about loan limits" value="{{ old('note') }}">
                            @error('note')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn" data-close-modal>Cancel</button>
                            <button class="btn primary" type="submit">Add</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
