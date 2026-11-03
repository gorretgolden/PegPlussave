{{-- Every loan the group has agreed to, whether the money has left the box yet or not. The row is the
     ledger: what was borrowed, what has come back, and how far behind the schedule it is today. --}}
@php
    use App\Support\Format;

    $chips = [
        'running' => 'Running',
        'arrears' => 'In arrears',
        'approval' => 'In approval',
        'closed' => 'Closed',
        'all' => 'All',
    ];
@endphp

<div class="sec">
    <x-chips :options="$chips" :current="$filter" />
    @if ($isTreasurer)
        <div class="sec-r">
            <button class="btn sm" data-open="dlg-repay"><x-icon name="check" />Record repayment</button>
            <button class="btn sm primary" data-open="dlg-loanbook-t"><x-icon name="plus" />Book a loan</button>
        </div>
    @endif
</div>

<div class="tbl">
    <table>
        <thead>
        <tr>
            <th>Loan</th>
            <th>Borrower</th>
            <th>Disbursed</th>
            <th class="num">Amount</th>
            <th>Period</th>
            <th class="num">Paid</th>
            <th class="num">Balance</th>
            <th class="num">Arrears</th>
            <th class="num">Days</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($loans as $row)
            @php $loan = $row['loan']; @endphp
            <tr class="click" data-open="dlg-loan-{{ $loan->id }}">
                <td class="mono">{{ $loan->ref }}</td>
                <td>
                    {{ $loan->member?->name }}
                    <span class="cell-sub">{{ $loan->purpose }}</span>
                </td>
                <td>{{ $loan->disbursed_on ? Format::date($loan->disbursed_on->toDateString()) : 'Pending' }}</td>
                <td class="num">{{ Format::money($loan->amount) }}</td>
                <td>{{ $loan->months }} months</td>
                <td class="num">{{ Format::money($row['paid']) }}</td>
                <td class="num">{{ Format::money($row['balance']) }}</td>
                <td class="num">@if ($row['arrears'])<span class="neg">{{ Format::money($row['arrears']) }}</span>@endif</td>
                <td class="num">{{ $row['daysLate'] ?: '' }}</td>
                <td><x-pill :tone="\App\Support\Trail::loanTone($row['state'])">{{ $row['state'] }}</x-pill></td>
            </tr>
        @endforeach
        @if ($loans->isEmpty())
            <x-empty-row :cols="10" message="No loans in this view." />
        @endif
        </tbody>
        @if ($loans->isNotEmpty())
            <tfoot>
            <tr>
                <td colspan="3">{{ $loans->count() }} loans</td>
                <td class="num">{{ Format::money($loans->sum(fn (array $r) => (int) $r['loan']->amount)) }}</td>
                <td></td>
                <td class="num">{{ Format::money($loans->sum('paid')) }}</td>
                <td class="num">{{ Format::money($loans->sum('balance')) }}</td>
                <td class="num">{{ Format::money($loans->sum('arrears')) }}</td>
                <td colspan="2"></td>
            </tr>
            </tfoot>
        @endif
    </table>
</div>
