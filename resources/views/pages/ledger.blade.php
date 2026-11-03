{{-- The cash book: only posted entries, only the ones that move money, in the order they happened.
     A month is chosen, the balance carried in from before it is shown once at the top, and the last
     column is that figure plus every line above it. --}}
@extends('layouts.peg')

@section('content')
    <x-conf-banner :can-make="$isTreasurer" />

    <div class="sec">
        <div></div>
        <div class="sec-r">
            <form method="GET" action="{{ route('pages.ledger') }}" class="inline" data-submit>
                <label class="sr" for="lm">Month</label>
                <select id="lm" name="month" class="btn sm">
                    <option value="all" @selected($month === 'all')>All months</option>
                    @foreach ($months as $option)
                        <option value="{{ $option }}" @selected($option === $month)>{{ \App\Support\Format::month($option) }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn sm" type="submit">Go</button></noscript>
            </form>
            <button class="btn sm" type="button" data-csv="{{ json_encode($csv) }}"><x-icon name="copy" />Copy as CSV</button>
            <a class="btn sm" href="{{ route('ledger.export', ['month' => $month]) }}">Download CSV</a>
        </div>
    </div>

    <div class="kpis">
        <x-kpi label="Brought forward" :value="\App\Support\Format::cash($forward)" />
        <x-kpi label="Receipts" :value="\App\Support\Format::cash($receipts)" tone="ok" />
        <x-kpi label="Payments" :value="\App\Support\Format::cash($payments)" tone="bad" />
        <x-kpi label="Carried forward" :value="\App\Support\Format::cash($carried)" />
    </div>

    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Ref</th>
                <th>Description</th>
                <th class="num">Receipts</th>
                <th class="num">Payments</th>
                <th class="num">Balance</th>
            </tr>
            </thead>
            <tbody>
            @if ($month !== 'all')
                <tr>
                    <td colspan="5" class="muted">Balance brought forward</td>
                    <td class="num">{{ \App\Support\Format::money($forward) }}</td>
                </tr>
            @endif
            @foreach ($lines as $line)
                <tr class="click" data-href="{{ route('entries.show', $line['transaction']) }}">
                    <td>{{ \App\Support\Format::date($line['date']) }}</td>
                    <td class="mono"><a href="{{ route('entries.show', $line['transaction']) }}">{{ $line['ref'] }}</a></td>
                    <td>{{ $line['description'] }}</td>
                    <td class="num">{{ $line['amount'] > 0 ? \App\Support\Format::money($line['amount']) : '' }}</td>
                    <td class="num">{{ $line['amount'] < 0 ? \App\Support\Format::money(-$line['amount']) : '' }}</td>
                    <td class="num">{{ \App\Support\Format::money($line['balance']) }}</td>
                </tr>
            @endforeach
            @if ($lines->isEmpty())
                <x-empty-row :cols="6" message="No cash movements in this month." />
            @endif
            </tbody>
        </table>
    </div>

    <section class="stack">
        <x-sec title="Non-cash postings"
               sub="Interest credited to members&#8217; accounts. It moves income to members without cash leaving PEG." />

        <div class="tbl fit">
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Ref</th>
                    <th>Description</th>
                    <th class="num">Amount</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($nonCash as $transaction)
                    <tr class="click" data-href="{{ route('entries.show', $transaction) }}">
                        <td>{{ \App\Support\Format::date($transaction->value_date->toDateString()) }}</td>
                        <td class="mono"><a href="{{ route('entries.show', $transaction) }}">{{ $transaction->ref }}</a></td>
                        <td>Interest for {{ \App\Support\Format::month((string) ($transaction->meta['month'] ?? '')) }}
                            &middot; {{ $transaction->allocations->count() }} savers</td>
                        <td class="num">{{ \App\Support\Format::money($transaction->amount) }}</td>
                    </tr>
                @endforeach
                @if ($nonCash->isEmpty())
                    <x-empty-row :cols="4" message="None in this period." />
                @endif
                </tbody>
            </table>
        </div>
    </section>
@endsection
