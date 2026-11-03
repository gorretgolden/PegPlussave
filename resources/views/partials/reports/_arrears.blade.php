{{-- The list the Chairperson reads out at a meeting: who has missed an installment, how long they
     have been owing it, and what the next due date now carries. --}}
<div class="sec">
    <div class="sub">
        Loans with at least one installment past its due date &middot; as at
        {{ \App\Support\Format::date(now()->toDateString()) }}
    </div>
    <div class="sec-r">@include('partials.reports._csv')</div>
</div>

<div class="kpis">
    <x-kpi
        label="Loans in arrears"
        :value="$late->count()"
        :sub="'of '.$runningCount.' running'"
        :tone="$late->isNotEmpty() ? 'bad' : 'ok'" />
    <x-kpi label="Amount in arrears" :value="\App\Support\Format::cash($lateAmount)" sub="Installments due and unpaid" tone="bad" />
    <x-kpi label="Principal at risk" :value="\App\Support\Format::cash($atRisk)" sub="Outstanding on these loans" />
    @php $oldest = $late->first(); @endphp
    <x-kpi
        label="Oldest"
        :value="$oldest ? $oldest['daysLate'].' days' : 'None'"
        :sub="$oldest ? $oldest['loan']->member->name : ''" />
</div>

<div class="tbl">
    <table>
        <thead>
        <tr>
            <th>Member</th>
            <th>Ext.</th>
            <th>Loan</th>
            <th class="num">Missed</th>
            <th class="num">Arrears</th>
            <th class="num">Days late</th>
            <th>Bucket</th>
            <th class="num">Balance</th>
            <th>Last paid</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($late as $ledger)
            @php $member = $ledger['loan']->member; @endphp
            <tr class="click" data-open="dlg-loan-{{ $ledger['loan']->id }}">
                <td>{{ $member->name }}<span class="cell-sub">{{ $member->department }}</span></td>
                <td class="mono">@if ($member->extension){{ $member->extension }}@endif</td>
                <td class="mono">{{ $ledger['loan']->ref }}</td>
                <td class="num">{{ $ledger['missed'] }}</td>
                <td class="num neg">{{ \App\Support\Format::money($ledger['arrears']) }}</td>
                <td class="num">{{ $ledger['daysLate'] }}</td>
                <td><x-pill :tone="$ledger['daysLate'] > 60 ? 'bad' : 'warn'">{{ $ledger['bucket'] }} days</x-pill></td>
                <td class="num">{{ \App\Support\Format::money($ledger['balance']) }}</td>
                <td>{{ $ledger['lastPay'] ? \App\Support\Format::date($ledger['lastPay']) : 'Never' }}</td>
            </tr>
        @endforeach
        @if ($late->isEmpty())
            <x-empty-row :cols="9" message="No loans in arrears." />
        @endif
        </tbody>
    </table>
</div>
