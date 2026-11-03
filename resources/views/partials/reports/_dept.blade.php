{{-- The same book seen department by department, which is how the committee finds out whether a
     savings drive worked where it was aimed. --}}
<div class="sec">
    <div class="sub">Membership, savings and loans by PlusSave department</div>
    <div class="sec-r">@include('partials.reports._csv')</div>
</div>

<div class="tbl">
    <table>
        <thead>
        <tr>
            <th>Department</th>
            <th class="num">Active</th>
            <th class="num">Left</th>
            <th class="num">Savings</th>
            <th>Share of savings</th>
            <th class="num">Running loans</th>
            <th class="num">Outstanding</th>
            <th class="num">In arrears</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td><b>{{ $row['dept'] }}</b></td>
                <td class="num">{{ $row['active'] }}</td>
                <td class="num">{{ $row['left'] }}</td>
                <td class="num">{{ \App\Support\Format::money($row['savings']) }}</td>
                <td>
                    <div class="hbar">
                        <i style="width:{{ $totalSavings > 0 ? min(100, round($row['savings'] / $totalSavings * 100)) : 0 }}%;background:var(--brand)"></i>
                    </div>
                </td>
                <td class="num">{{ $row['loans'] }}</td>
                <td class="num">{{ \App\Support\Format::money($row['outstanding']) }}</td>
                <td class="num">
                    @if ($row['inArrears'])
                        <span class="neg">{{ $row['inArrears'] }}</span>
                    @else
                        0
                    @endif
                </td>
            </tr>
        @endforeach
        @if ($rows === [])
            <x-empty-row :cols="8" message="No members yet." />
        @endif
        </tbody>
        <tfoot>
        <tr>
            <td>All departments</td>
            <td class="num">{{ array_sum(array_column($rows, 'active')) }}</td>
            <td class="num">{{ array_sum(array_column($rows, 'left')) }}</td>
            <td class="num">{{ \App\Support\Format::money($totalSavings) }}</td>
            <td></td>
            <td class="num">{{ array_sum(array_column($rows, 'loans')) }}</td>
            <td class="num">{{ \App\Support\Format::money(array_sum(array_column($rows, 'outstanding'))) }}</td>
            <td class="num">{{ array_sum(array_column($rows, 'inArrears')) }}</td>
        </tr>
        </tfoot>
    </table>
</div>
