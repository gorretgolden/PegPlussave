{{-- The checking desk. A queue is read top to bottom in the order it arrived, because the oldest
     entry is the one holding up a member's money. Decided is a log of this officer's own yes and no,
     not a second opinion on the same figures. --}}
@extends('layouts.peg')

@php
    $badge = fn (int $count) => ' <span class="tn">'.$count.'</span>';

    $tabs = [
        'mine' => 'Waiting for you'.$badge($mine->count()),
        'other' => ($isPatronSide ? 'With the Chairperson' : 'With the Patron').$badge($other->count()),
        'decided' => 'Decided by you',
    ];
@endphp

@section('content')
    <div class="rules">
        <x-icon name="shield" />
        <span>{{ $isPatronSide
            ? 'You authorise the exceptional cases after the Chairperson has checked them.'
            : 'You are the checker for every entry the Treasurer makes. Loans above '
                .\App\Support\Format::ugx((int) \App\Support\Policy::get('patron_threshold'))
                .', policy exceptions, external borrowing, exits and the annual payout go on to the Patron after you.' }}</span>
    </div>

    <x-tabs :options="$tabs" :current="$tab" label="Approval queues" />

    @if ($tab === 'mine')
        @if ($mine->isEmpty())
            <div class="panel empty">Nothing is waiting for you. New entries appear here as soon as they are made.</div>
        @else
            <div class="ap-list">
                @foreach ($mine as $transaction)
                    @include('partials._txn-card', [
                        'actionable' => true,
                        'balances' => $balances,
                        'ledgers' => $ledgers,
                    ])
                @endforeach
            </div>
        @endif
    @elseif ($tab === 'other')
        @if ($other->isEmpty())
            <div class="panel empty">Nothing in this queue.</div>
        @else
            <div class="ap-list">
                @foreach ($other as $transaction)
                    @include('partials._txn-card', [
                        'actionable' => false,
                        'balances' => $balances,
                        'ledgers' => $ledgers,
                    ])
                @endforeach
            </div>
        @endif
    @else
        <div class="tbl">
            <table>
                <thead>
                <tr>
                    <th>Ref</th>
                    <th>Type</th>
                    <th>Party</th>
                    <th class="num">Amount</th>
                    <th>Your decision</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($decided as $transaction)
                    @php $last = $transaction->checks->filter(fn ($check) => $check->user_id === auth()->id())->last(); @endphp
                    <tr class="click" data-href="{{ route('entries.show', $transaction) }}">
                        <td class="mono"><a href="{{ route('entries.show', $transaction) }}">{{ $transaction->ref }}</a></td>
                        <td>{{ $transaction->typeLabel() }}</td>
                        <td>{{ $transaction->party() }}</td>
                        <td class="num">{{ \App\Support\Format::money($transaction->amount) }}</td>
                        <td>{{ $last ? ucfirst($last->action) : '' }} &middot; {{ \App\Support\Format::dateTime($last?->checked_at?->toDateTimeString()) }}</td>
                        <td><x-pill :tone="\App\Support\Trail::transactionTone($transaction->status)">{{ $transaction->statusLabel() }}</x-pill></td>
                    </tr>
                @endforeach
                @if ($decided->isEmpty())
                    <x-empty-row :cols="6" message="No decisions yet." />
                @endif
                </tbody>
            </table>
        </div>
    @endif
@endsection
