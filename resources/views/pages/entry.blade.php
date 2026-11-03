{{-- One entry, opened. It is the same card the checker works from, with the note box under it, so the
     question somebody asks about a figure lives on the figure rather than in a chat thread. --}}
@extends('layouts.peg')

@section('content')
    <div class="sec">
        <div>
            <h2>{{ $transaction->typeLabel() }} {{ $transaction->ref }}</h2>
            <div class="sub">Made by {{ $transaction->maker?->name }} &middot;
                {{ \App\Support\Format::dateTime($transaction->made_at?->toDateTimeString()) }}</div>
        </div>
        <div class="sec-r">
            <a class="btn sm" href="{{ url()->previous() }}">Back</a>
        </div>
    </div>

    <div class="ap-list">
        @include('partials._txn-card', [
            'actionable' => $canCheck,
            'balances' => $balances,
            'ledgers' => $ledgers,
        ])
    </div>

    @if (auth()->user()->can('view-audit'))
        <form method="POST" action="{{ route('approvals.note', $transaction) }}" class="form">
            @csrf
            <div class="field">
                <label for="n-t">{{ auth()->user()->hasRole('accountant') ? 'Raise a query or review note' : 'Add a note' }}</label>
                <textarea id="n-t" name="text" required placeholder="Visible to the treasury team">{{ old('text') }}</textarea>
                @error('text')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Add note</button>
            </div>
        </form>
    @endif
@endsection
