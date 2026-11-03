{{-- A request is a member asking, not the group agreeing. The seven checks are printed here so the
     appraisal is a decision about figures that are already on the table, not a signature on a name. --}}
<section class="stack">
    <x-sec :title="'Waiting for appraisal'">
        <x-slot:actions>
            <span class="sub">{{ $waiting->count() }} requests booked by members</span>
        </x-slot:actions>
    </x-sec>
    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>Loan</th>
                <th>Member</th>
                <th>Requested</th>
                <th class="num">Amount</th>
                <th>Period</th>
                <th>Purpose</th>
                <th class="num">Savings</th>
                <th>Checks</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($waiting as $row)
                @php $loan = $row['loan']; @endphp
                <tr>
                    <td class="mono">{{ $loan->ref }}</td>
                    <td>
                        {{ $loan->member->name }}
                        <span class="cell-sub">{{ $loan->member->department }}</span>
                    </td>
                    <td>{{ \App\Support\Format::dateTime($loan->requested_at?->toDateTimeString()) }}</td>
                    <td class="num">{{ \App\Support\Format::money($loan->amount) }}</td>
                    <td>{{ $loan->months }} months</td>
                    <td>{{ $loan->purpose }}</td>
                    <td class="num">{{ \App\Support\Format::money($loan->member->id ? ($group['balances'][$loan->member_id]['balance'] ?? 0) : 0) }}</td>
                    <td>
                        <x-pill :tone="$row['met'] === count($row['checks']) ? 'ok' : 'warn'">
                            {{ $row['met'] }} of {{ count($row['checks']) }} met
                        </x-pill>
                    </td>
                    <td class="row-act">
                        @if ($isTreasurer)
                            <button class="btn sm primary" data-open="dlg-appraise-{{ $loan->id }}">Appraise</button>
                        @else
                            <button class="btn sm" data-open="dlg-loan-{{ $loan->id }}">View</button>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if ($waiting->isEmpty())
                <x-empty-row :cols="9" message="No requests waiting." />
            @endif
            </tbody>
        </table>
    </div>
</section>

<section class="stack">
    <x-sec title="Recent member requests" />
    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>Loan</th>
                <th>Member</th>
                <th>Requested</th>
                <th class="num">Amount</th>
                <th>Status</th>
                <th>Trail</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($decided as $loan)
                @php $booking = $loan->booking()->first(); @endphp
                <tr class="click" data-open="dlg-loan-{{ $loan->id }}">
                    <td class="mono">{{ $loan->ref }}</td>
                    <td>{{ $loan->member?->name }}</td>
                    <td>{{ \App\Support\Format::date($loan->requested_at?->toDateString()) }}</td>
                    <td class="num">{{ \App\Support\Format::money($loan->amount) }}</td>
                    <td>
                        @php $state = \App\Support\Trail::loanState($loan, $ledgers[$loan->id] ?? null); @endphp
                        <x-pill :tone="\App\Support\Trail::loanTone($state)">{{ $state }}</x-pill>
                    </td>
                    <td>@if ($booking)<x-mini-trail :dots="\App\Support\Trail::mini($booking)" />@endif</td>
                </tr>
            @endforeach
            @if ($decided->isEmpty())
                <x-empty-row :cols="6" message="None yet." />
            @endif
            </tbody>
        </table>
    </div>
</section>
