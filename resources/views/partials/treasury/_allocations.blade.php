{{-- The share list a posting or a payout is made of. It is opened rather than shown because a
     hundred names in a form box hide the four figures the officer is deciding on. --}}
@if ($allocations !== [])
    @php
        $names = \App\Models\Member::query()
            ->whereIn('id', array_column($allocations, 'member_id'))
            ->pluck('name', 'id');
    @endphp
    <details class="more">
        <summary>{{ $heading }}</summary>
        <div class="tbl fit">
            <table>
                <thead>
                <tr>
                    <th>Member</th>
                    @if (isset($allocations[0]['base']) && $allocations[0]['base'] !== null)
                        <th class="num">Savings base</th>
                    @endif
                    <th class="num">Amount</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($allocations as $row)
                    <tr>
                        <td>{{ $names[$row['member_id']] ?? '' }}</td>
                        @if (isset($allocations[0]['base']) && $allocations[0]['base'] !== null)
                            <td class="num">{{ \App\Support\Format::money($row['base']) }}</td>
                        @endif
                        <td class="num">{{ \App\Support\Format::money($row['amount']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </details>
@endif
