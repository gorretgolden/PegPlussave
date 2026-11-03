{{-- What the group may lend today, and what it owns against what it owes. The waterfall is the only
     place the reserve and the in-flight bookings are taken out, so the figure at the bottom is the
     one a Treasurer can commit. --}}
@php
    $reservePercent = (int) $settings['reserve_percent'];
    $label = fn (string $type) => \App\Models\Transaction::TYPE_LABELS[$type] ?? $type;
@endphp

<div class="sec">
    <div class="sub">
        How much PEG can lend today, and what it owns and owes &middot; as at
        {{ \App\Support\Format::date(now()->toDateString()) }}
    </div>
    <div class="sec-r">@include('partials.reports._csv')</div>
</div>

<div class="cols2">
    <section class="panel">
        <x-sec title="Available for new loans" />
        <div class="wf">
            <div class="wf-row">
                <span>Cash at bank (book)<span class="cell-sub">All posted receipts less payments</span></span>
                <span class="num">{{ \App\Support\Format::money($group['cash']) }}</span>
            </div>
            <div class="wf-row">
                <span>Less liquidity reserve<span class="cell-sub">{{ $reservePercent }}% of members' savings, kept for exits</span></span>
                <span class="num neg">&minus;{{ \App\Support\Format::money($group['reserve']) }}</span>
            </div>
            <div class="wf-row">
                <span>Less loans in the approval chain<span class="cell-sub">Booked, waiting for checks</span></span>
                <span class="num neg">&minus;{{ \App\Support\Format::money($group['committed']) }}</span>
            </div>
            <div class="wf-row total">
                <span>Available to lend</span>
                <span class="num">{{ \App\Support\Format::money($group['available']) }}</span>
            </div>
        </div>
    </section>

    <section class="panel">
        <x-sec title="Statement of financial position" />
        <div class="tbl fit">
            <table class="pos-table">
                <tbody>
                <tr><td>Cash at bank</td><td class="num">{{ \App\Support\Format::money($group['cash']) }}</td></tr>
                <tr><td>Loans outstanding (principal)</td><td class="num">{{ \App\Support\Format::money($group['outstanding_principal']) }}</td></tr>
                </tbody>
                <tfoot>
                <tr><td>Total assets</td><td class="num">{{ \App\Support\Format::money($assets) }}</td></tr>
                </tfoot>
            </table>
        </div>

        <div class="tbl fit" style="margin-top:12px">
            <table class="pos-table">
                <tbody>
                <tr><td>Members' savings</td><td class="num">{{ \App\Support\Format::money($group['total_savings']) }}</td></tr>
                <tr><td>Interest due to members</td><td class="num">{{ \App\Support\Format::money($group['interest_due']) }}</td></tr>
                <tr><td>External borrowing</td><td class="num">{{ \App\Support\Format::money($group['external_outstanding']) }}</td></tr>
                <tr><td>Retained income (reserve fund)</td><td class="num">{{ \App\Support\Format::money($group['retained']) }}</td></tr>
                </tbody>
                <tfoot>
                <tr>
                    <td>Total liabilities and reserves</td>
                    <td class="num">{{ \App\Support\Format::money($liabilities + $group['retained']) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>

<section class="stack">
    <x-sec :title="'Cash movements in '.$year" />

    <div class="tbl fit">
        <table>
            <thead>
            <tr>
                <th>Type</th>
                <th class="num">Receipts</th>
                <th class="num">Payments</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($flows as $type => $amount)
                <tr>
                    <td>{{ $label($type) }}</td>
                    <td class="num">{{ $amount > 0 ? \App\Support\Format::money($amount) : '' }}</td>
                    <td class="num">{{ $amount < 0 ? \App\Support\Format::money(-$amount) : '' }}</td>
                </tr>
            @endforeach
            @if ($flows->isEmpty())
                <x-empty-row :cols="3" :message="'No cash moved in '.$year.'.'" />
            @endif
            </tbody>
            <tfoot>
            <tr>
                <td>Net movement</td>
                <td class="num" colspan="2">{{ \App\Support\Format::money($net) }}</td>
            </tr>
            </tfoot>
        </table>
    </div>
</section>

<div class="rules">
    <x-icon name="percent" />
    <span>
        Loan interest earned so far: <b>{{ \App\Support\Format::ugx($group['interest_collected']) }}</b>.
        Paid to lenders: <b>{{ \App\Support\Format::ugx($group['external_interest_paid']) }}</b>.
        Shared to savers: <b>{{ \App\Support\Format::ugx($group['distributed']) }}</b>.
        The rest stays in PEG as retained income.
    </span>
</div>
