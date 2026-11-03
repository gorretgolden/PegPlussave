{{-- What each saver was credited, month by month, from the postings that were actually approved. The
     row totals are the accounts the Treasurer will settle when a member leaves or takes a payout. --}}
@php
    $payoutMonth = \App\Support\Format::monthName($year.'-'.str_pad((string) $settings['payout_month'], 2, '0', STR_PAD_LEFT));
    $earnedTotal = 0;
    $paidTotal = 0;

    foreach ($members as $member) {
        $bal = $group['balances'][$member->id] ?? [];
        $earnedTotal += (int) ($bal['interest_earned'] ?? 0);
        $paidTotal += (int) ($bal['interest_paid_out'] ?? 0);
    }
@endphp

<div class="sec">
    <div class="sub">
        Interest credited to each saver every month in {{ $year }}, from net loan interest shared pro
        rata to savings
    </div>
    <div class="sec-r">@include('partials.reports._csv')</div>
</div>

<div class="kpis">
    <x-kpi label="Interest shared" :value="\App\Support\Format::cash($distributed)" :sub="$columns->count().' monthly postings'" />
    <x-kpi label="Withdrawn" :value="\App\Support\Format::cash($withdrawn)" sub="Payouts and exits" />
    <x-kpi label="Due to members" :value="\App\Support\Format::cash($group['interest_due'])" :sub="'Payout in '.$payoutMonth" tone="gold" />
    <x-kpi label="Average per saver" :value="\App\Support\Format::cash($average)" sub="Accumulated, active savers" />
</div>

<div class="tbl">
    <table>
        <thead>
        <tr>
            <th>Member</th>
            @foreach ($columns as $column)
                <th class="num">{{ $column['label'] }}</th>
            @endforeach
            <th class="num">Earned</th>
            <th class="num">Withdrawn</th>
            <th class="num">Balance</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($members as $member)
            @php $bal = $group['balances'][$member->id] ?? []; @endphp
            <tr class="click" data-open="dlg-member-{{ $member->id }}">
                <td>
                    {{ $member->name }}
                    @if ($member->status === 'left') <x-pill tone="muted">Left</x-pill> @endif
                </td>
                @foreach ($columns as $column)
                    <td class="num">{{ \App\Support\Format::money($shares[$column['id']][$member->id] ?? 0) }}</td>
                @endforeach
                <td class="num">{{ \App\Support\Format::money((int) ($bal['interest_earned'] ?? 0)) }}</td>
                <td class="num">{{ \App\Support\Format::money((int) ($bal['interest_paid_out'] ?? 0)) }}</td>
                <td class="num"><b>{{ \App\Support\Format::money((int) ($bal['interest_earned'] ?? 0) - (int) ($bal['interest_paid_out'] ?? 0)) }}</b></td>
            </tr>
        @endforeach
        @if ($members->isEmpty())
            <x-empty-row :cols="$columns->count() + 4" message="No saver has been credited interest in this year." />
        @endif
        </tbody>
        <tfoot>
        <tr>
            <td>Total</td>
            @foreach ($columns as $column)
                <td class="num">{{ \App\Support\Format::money($column['amount']) }}</td>
            @endforeach
            <td class="num">{{ \App\Support\Format::money($earnedTotal) }}</td>
            <td class="num">{{ \App\Support\Format::money($paidTotal) }}</td>
            <td class="num">{{ \App\Support\Format::money($earnedTotal - $paidTotal) }}</td>
        </tr>
        </tfoot>
    </table>
</div>
