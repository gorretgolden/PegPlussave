{{-- Every loan the group paid out in one year, in the order the money left the box. The trail in the
     Approvals column is the booking entry, which is the paper behind the disbursement. --}}
<div class="sec">
    <div>
        <div class="sub">Loans paid out to members in {{ $year }}</div>
    </div>
    <div class="sec-r">
        <form method="GET" action="{{ route('pages.reports') }}" class="inline" data-submit>
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="sr" for="rep-y">Year</label>
            <select id="rep-y" name="year" class="btn sm">
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                @endforeach
            </select>
            <noscript><button class="btn sm" type="submit">Go</button></noscript>
        </form>
        @include('partials.reports._csv')
    </div>
</div>

<div class="kpis">
    <x-kpi label="Loans disbursed" :value="$loans->count()" />
    <x-kpi label="Total disbursed" :value="\App\Support\Format::cash($total)" />
    <x-kpi label="Average loan" :value="\App\Support\Format::cash($average)" />
    <x-kpi label="Largest loan" :value="\App\Support\Format::cash($largest)" />
</div>

<section class="panel">
    <x-sec title="By month" />
    <x-bars :rows="$chart" :series="[['key' => 'a', 'color' => 'var(--brand)', 'name' => 'Disbursed']]" aria="Loans disbursed by month" />
</section>

<div class="tbl">
    <table>
        <thead>
        <tr>
            <th>Disbursed</th>
            <th>Loan</th>
            <th>Member</th>
            <th class="num">Amount</th>
            <th>Period</th>
            <th>Purpose</th>
            <th>Approvals</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($loans as $loan)
            @php $booking = $loan->booking->first(); @endphp
            <tr class="click" data-open="dlg-loan-{{ $loan->id }}">
                <td>{{ \App\Support\Format::date($loan->disbursed_on->toDateString()) }}</td>
                <td class="mono">{{ $loan->ref }}</td>
                <td>{{ $loan->member->name }}</td>
                <td class="num">{{ \App\Support\Format::money($loan->amount) }}</td>
                <td>{{ $loan->months }} months</td>
                <td>{{ $loan->purpose }}</td>
                <td>@if ($booking)<x-mini-trail :dots="\App\Support\Trail::mini($booking)" />@endif</td>
                <td>
                    @if (($state = $states[$loan->id] ?? null))
                        <x-pill :tone="\App\Support\Trail::loanTone($state)">{{ $state }}</x-pill>
                    @endif
                </td>
            </tr>
        @endforeach
        @if ($loans->isEmpty())
            <x-empty-row :cols="8" message="No loans disbursed in this year." />
        @endif
        </tbody>
        @if ($loans->isNotEmpty())
            <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td class="num">{{ \App\Support\Format::money($total) }}</td>
                <td colspan="4"></td>
            </tr>
            </tfoot>
        @endif
    </table>
</div>
