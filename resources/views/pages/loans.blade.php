@extends('layouts.peg')

@section('content')
    <section class="panel">
        <x-sec title="Loan terms for you">
            <x-slot:actions>
                <button class="btn primary" data-open="dlg-loanbook"><x-icon name="plus" />Book a loan</button>
            </x-slot:actions>
        </x-sec>
        @php
            $terms = [
                ['Interest', \App\Support\Format::rate($policy['rate']).' a month, '.($policy['method'] === 'flat' ? 'flat on the amount' : 'on the reducing balance')],
                ['Your limit', '<span class="mono">'.\App\Support\Format::ugx($balance * $policy['multiple']).'</span>'],
                ['Limit rule', $policy['multiple'].'× your savings'],
                ['Repayment period', \App\Support\Policy::tenureBandsText()],
                ['Approvals', 'Treasurer, then Chairperson. Above '.\App\Support\Format::ugx($policy['patronThreshold']).', also the Patron.'],
            ];
        @endphp
        <x-kv :pairs="$terms" />
    </section>

    @if ($running)
        @include('partials._loan-card', ['ledger' => $running, 'showDetails' => false])
    @endif

    <x-sec title="My loans and requests" />

    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>Loan</th>
                <th>Requested</th>
                <th class="num">Amount</th>
                <th>Period</th>
                <th>Purpose</th>
                <th>Status</th>
                <th>Progress</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($loans as $loan)
                @php
                    $ledger = $ledgers[$loan->id] ?? null;
                    $state = \App\Support\Trail::loanState($loan, $ledger);
                    $progress = match (true) {
                        (bool) $loan->disbursed_on => ($ledger && $ledger['totalDue'] > 0)
                            ? round(($ledger['totalDue'] - $ledger['balance']) / $ledger['totalDue'] * 100).'% repaid'
                            : '',
                        $loan->status === \App\Models\Loan::DECLINED => e($loan->appraisal_note ?: ''),
                        default => 'In the approval chain',
                    };
                @endphp
                <tr class="click" data-open="dlg-loan-{{ $loan->id }}">
                    <td class="mono">{{ $loan->ref }}</td>
                    <td>{{ \App\Support\Format::date($loan->requested_at?->toDateString()) }}</td>
                    <td class="num">{{ \App\Support\Format::money($loan->amount) }}</td>
                    <td>{{ $loan->months }} months</td>
                    <td>{{ $loan->purpose }}</td>
                    <td><x-pill :tone="\App\Support\Trail::loanTone($state)">{{ $state }}</x-pill></td>
                    <td>{{ $progress }}</td>
                </tr>
            @endforeach
            @if ($loans->isEmpty())
                <x-empty-row :cols="7" message="You have not booked a loan yet." />
            @endif
            </tbody>
        </table>
    </div>
@endsection

@section('modals')
    @foreach ($loans as $loan)
        @include('partials._loan-modal', [
            'ledger' => $ledgers[$loan->id] ?? $loan->ledger(),
            'treasuryActions' => false,
        ])
    @endforeach
@endsection
