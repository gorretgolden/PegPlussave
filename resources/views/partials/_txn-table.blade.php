{{-- The one entry table every treasury list is drawn with: what it is, whose money it is, who typed
     it, how far it has travelled, and whether it has been posted. The row opens the entry itself. --}}
@php
    $rows = $transactions ?? $entries ?? collect();
@endphp
<div class="tbl">
    <table>
        <thead>
        <tr>
            <th>Ref</th>
            <th>Value date</th>
            <th>Type</th>
            <th>Party</th>
            <th class="num">Amount</th>
            <th>Made by</th>
            <th>Trail</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $transaction)
            <tr class="click" data-href="{{ route('entries.show', $transaction) }}">
                <td class="mono">
                    <a href="{{ route('entries.show', $transaction) }}">{{ $transaction->ref }}</a>
                    @if ($transaction->notes->count())
                        <span class="pill info" title="Has notes">{{ $transaction->notes->count() }}</span>
                    @endif
                </td>
                <td>{{ \App\Support\Format::date($transaction->value_date->toDateString()) }}</td>
                <td>{{ $transaction->typeLabel() }}</td>
                <td>{{ $transaction->party() }}</td>
                <td class="num">{{ \App\Support\Format::money($transaction->amount) }}</td>
                <td>
                    {{ $transaction->maker?->name }}
                    <span class="cell-sub">{{ \App\Support\Format::dateTime($transaction->made_at?->toDateTimeString()) }}</span>
                </td>
                <td><x-mini-trail :dots="\App\Support\Trail::mini($transaction)" /></td>
                <td><x-pill :tone="\App\Support\Trail::transactionTone($transaction->status)">{{ $transaction->statusLabel() }}</x-pill></td>
            </tr>
        @endforeach
        @if ($rows->isEmpty())
            <x-empty-row :cols="8" message="No entries." />
        @endif
        </tbody>
    </table>
</div>
