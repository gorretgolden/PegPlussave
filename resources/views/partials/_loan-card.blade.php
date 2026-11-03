{{-- One loan as the group reads it: the terms it was booked on, how much of it is behind, and the
     installment table the repayments have been applied to. --}}
@php
    $loan = $ledger['loan'];
    $next = $ledger['next'];
    $pct = $ledger['totalDue'] > 0 ? (int) round(($ledger['totalDue'] - $ledger['balance']) / $ledger['totalDue'] * 100) : 0;
    $interestTotal = array_sum(array_column($ledger['schedule'], 'interest'));
    $state = \App\Support\Trail::loanState($loan, $ledger);
    $dueNext = $next ? $next['total'] - $next['paidInterest'] - $next['paidPrincipal'] : 0;
@endphp
<section class="panel stack">
    <x-sec :title="$loan->ref.' · '.\App\Support\Format::ugx($loan->amount).' over '.$loan->months.' months'"
           :sub="$loan->purpose.' · disbursed '.\App\Support\Format::date($loan->disbursed_on?->toDateString()).' · '.\App\Support\Format::rate($loan->rate).' a month '.$loan->method">
        <x-slot:actions>
            <x-pill :tone="\App\Support\Trail::loanTone($state)">{{ $state }}</x-pill>
            @if (! empty($showDetails))
                <button class="btn sm" data-open="dlg-loan-{{ $loan->id }}">Details</button>
            @endif
        </x-slot:actions>
    </x-sec>

    <div class="kpis k3 flat">
        <x-kpi label="Balance" :value="\App\Support\Format::cash($ledger['balance'])" :sub="$pct.'% repaid'" />
        @if ($ledger['arrears'] > 0)
            <x-kpi label="In arrears" :value="\App\Support\Format::cash($ledger['arrears'])"
                   :sub="$ledger['daysLate'].' days overdue · '.$ledger['missed'].' missed'" tone="bad" />
        @else
            <x-kpi label="Next installment" :value="\App\Support\Format::cash($dueNext)"
                   :sub="$next ? 'Due '.\App\Support\Format::date($next['due']) : ''" />
        @endif
        <x-kpi label="Interest paid" :value="\App\Support\Format::cash($ledger['interestPaid'])"
               :sub="'of '.\App\Support\Format::ugx($interestTotal)" />
    </div>

    <div class="prog" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
        <i style="width:{{ $pct }}%"></i>
    </div>

    @include('partials._schedule-table', ['ledger' => $ledger])
</section>
