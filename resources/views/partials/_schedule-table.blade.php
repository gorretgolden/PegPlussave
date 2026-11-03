{{-- The installment table a loan was booked on, with what has actually landed against it. The
     schedule is rebuilt from the terms each time, so this cannot show an installment the maths
     does not support. --}}
@php
    $today = now()->toDateString();
    $interestTotal = array_sum(array_column($ledger['schedule'], 'interest'));
@endphp
<div class="tbl">
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>Due</th>
            <th class="num">Principal</th>
            <th class="num">Interest</th>
            <th class="num">Installment</th>
            <th class="num">Paid</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($ledger['schedule'] as $row)
            @php
                $paid = $row['paidInterest'] + $row['paidPrincipal'];
                $status = match (true) {
                    $paid >= $row['total'] => ['ok', 'Paid'],
                    $row['due'] < $today => ['bad', 'Overdue'],
                    $paid > 0 => ['warn', 'Part paid'],
                    default => ['muted', 'Upcoming'],
                };
            @endphp
            <tr>
                <td class="mono">{{ $row['number'] }}</td>
                <td>{{ \App\Support\Format::date($row['due']) }}</td>
                <td class="num">{{ \App\Support\Format::money($row['principal']) }}</td>
                <td class="num">{{ \App\Support\Format::money($row['interest']) }}</td>
                <td class="num">{{ \App\Support\Format::money($row['total']) }}</td>
                <td class="num">{{ \App\Support\Format::money($paid) }}</td>
                <td><x-pill :tone="$status[0]">{{ $status[1] }}</x-pill></td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td colspan="2">Total</td>
            <td class="num">{{ \App\Support\Format::money($ledger['loan']->amount) }}</td>
            <td class="num">{{ \App\Support\Format::money($interestTotal) }}</td>
            <td class="num">{{ \App\Support\Format::money($ledger['totalDue']) }}</td>
            <td class="num">{{ \App\Support\Format::money($ledger['paid']) }}</td>
            <td></td>
        </tr>
        </tfoot>
    </table>
</div>
