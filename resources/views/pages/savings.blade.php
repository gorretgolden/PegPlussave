@extends('layouts.peg')

@php
    $figure = fn (?int $n) => $n ? '<span class="'.($n < 0 ? 'neg' : '').'">'.($n < 0 ? '−' : '').\App\Support\Format::money(abs($n)).'</span>' : '';
@endphp

@section('content')
    <div class="kpis">
        <x-kpi label="Principal savings" :value="\App\Support\Format::cash($row['balance'])"
               :sub="count($row['months']).' months saved'" />
        <x-kpi label="Interest earned" :value="\App\Support\Format::cash($row['interest_earned'])"
               sub="Since you joined" />
        <x-kpi label="Interest withdrawn" :value="\App\Support\Format::cash($row['interest_paid_out'])"
               sub="Annual payouts" />
        <x-kpi label="Interest available" :value="\App\Support\Format::cash($row['interest'])"
               :sub="'Paid out in '.\Carbon\CarbonImmutable::parse('2000-'.str_pad((string) $settings['payout_month'], 2, '0', STR_PAD_LEFT).'-01')->format('F')" tone="gold" />
    </div>

    <div class="rules">
        <x-icon name="info" />
        <span>Save at least <b>{{ \App\Support\Format::ugx($settings['min_monthly']) }}</b> by the
            {{ \App\Support\Format::ordinal($settings['contribution_day']) }} of each month. Your principal stays in
            PEG until you leave PlusSave. Interest is shared every month and withdrawn once a year.</span>
        <button class="btn sm" data-open="dlg-paynotice"><x-icon name="upload" />Notify a payment</button>
    </div>

    <section class="panel">
        <x-sec :title="'Saved and earned in '.$year">
            <x-slot:actions>
                <x-legend :items="[['var(--brand)', 'Saved'], ['var(--gold)', 'Interest earned']]" />
            </x-slot:actions>
        </x-sec>
        <x-bars :rows="$chart" :series="[
            ['key' => 'saved', 'color' => 'var(--brand)', 'name' => 'Saved'],
            ['key' => 'interest', 'color' => 'var(--gold)', 'name' => 'Interest'],
        ]" aria="Savings and interest by month" />
    </section>

    <x-sec title="Statement">
        <x-slot:actions>
            <span class="sub">{{ count($statement) }} entries</span>
        </x-slot:actions>
    </x-sec>

    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Ref</th>
                <th>Description</th>
                <th class="num">Savings</th>
                <th class="num">Interest</th>
                <th class="num">Savings balance</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($statement as $entry)
                <tr>
                    <td>{{ \App\Support\Format::date($entry['transaction']->value_date->toDateString()) }}</td>
                    <td class="mono">{{ $entry['transaction']->ref }}</td>
                    <td>{{ $entry['description'] }}</td>
                    <td class="num">{!! $figure($entry['savings']) !!}</td>
                    <td class="num">{!! $figure($entry['interest']) !!}</td>
                    <td class="num">{{ \App\Support\Format::money($entry['balance']) }}</td>
                    <td><x-pill :tone="\App\Support\Trail::transactionTone($entry['transaction']->status)">{{ $entry['transaction']->statusLabel() }}</x-pill></td>
                </tr>
            @endforeach
            @if ($statement === [])
                <x-empty-row :cols="7" message="No savings yet." />
            @endif
            </tbody>
        </table>
    </div>
@endsection
