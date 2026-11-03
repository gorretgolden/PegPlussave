{{-- Portfolio at risk. A loan counts against a bucket only when an installment is late by MORE than
     the days shown, which is why PAR 1 is the widest of the four. --}}
<div class="sec">
    <div class="sub">
        Share of outstanding principal on loans with an installment late by more than the number of
        days shown &middot; as at {{ \App\Support\Format::date(now()->toDateString()) }}
    </div>
    <div class="sec-r">@include('partials.reports._csv')</div>
</div>

<div class="kpis">
    @foreach ($tiles as $tile)
        <x-kpi
            :label="$tile['label']"
            :value="\App\Support\Format::percent($tile['percent'])"
            :sub="\App\Support\Format::ugx($tile['amount']).' at risk'"
            :tone="$tile['tone']" />
    @endforeach
</div>

<div class="tbl">
    <table>
        <thead>
        <tr>
            <th>Ageing bucket</th>
            <th class="num">Loans</th>
            <th class="num">Outstanding principal</th>
            <th class="num">Share</th>
            <th>Distribution</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($buckets as $bucket)
            <tr>
                <td><b>{{ $bucket['label'] }}</b></td>
                <td class="num">{{ $bucket['count'] }}</td>
                <td class="num">{{ \App\Support\Format::money($bucket['value']) }}</td>
                <td class="num">{{ \App\Support\Format::percent($bucket['percent']) }}</td>
                <td>
                    <div class="hbar">
                        <i style="width:{{ min(100, round($bucket['percent'], 1)) }}%;background:{{ $bucket['color'] }}"></i>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td>Total portfolio</td>
            <td class="num">{{ count($group['running']) }}</td>
            <td class="num">{{ \App\Support\Format::money($total) }}</td>
            <td class="num">100%</td>
            <td></td>
        </tr>
        </tfoot>
    </table>
</div>

<section class="stack">
    <x-sec title="Loans by risk" />

    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>Loan</th>
                <th>Borrower</th>
                <th class="num">Outstanding principal</th>
                <th class="num">Days late</th>
                <th>Bucket</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($risk as $ledger)
                <tr class="click" data-open="dlg-loan-{{ $ledger['loan']->id }}">
                    <td class="mono">{{ $ledger['loan']->ref }}</td>
                    <td>{{ $ledger['loan']->member->name }}</td>
                    <td class="num">{{ \App\Support\Format::money($ledger['outstandingPrincipal']) }}</td>
                    <td class="num">{{ $ledger['daysLate'] }}</td>
                    <td>
                        <x-pill :tone="\App\Support\Trail::loanTone($ledger['state'])">{{ $ledger['state'] }}</x-pill>
                        @if ($ledger['daysLate']) <span class="cell-sub">{{ $ledger['bucket'] }}</span> @endif
                    </td>
                </tr>
            @endforeach
            @if ($risk->isEmpty())
                <x-empty-row :cols="5" message="No loan has an installment past its due date." />
            @endif
            </tbody>
        </table>
    </div>
</section>
