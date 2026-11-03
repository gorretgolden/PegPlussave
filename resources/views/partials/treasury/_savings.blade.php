{{-- The two things a Treasurer watches every month: members who say they have paid and have not yet
     been written into the book, and the savings balance each member is earning interest on. --}}
@php
    use App\Support\Format;
@endphp

<section class="stack">
    <x-sec title="Payment notices from members"
           sub="Members tell the Treasurer when they pay directly. Recording one creates a deposit for checking.">
        <x-slot:actions>
            <span class="sub">{{ $notices->count() }} to record</span>
        </x-slot:actions>
    </x-sec>
    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>Notice</th>
                <th>Member</th>
                <th>Paid on</th>
                <th>Channel</th>
                <th>Reference</th>
                <th class="num">Amount</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($notices as $notice)
                <tr>
                    <td class="mono">{{ $notice->ref }}</td>
                    <td>
                        {{ $notice->member?->name }}
                        <span class="cell-sub">For {{ Format::month($notice->month) }}</span>
                    </td>
                    <td>{{ Format::date($notice->paid_on?->toDateString()) }}</td>
                    <td>{{ $notice->channel }}</td>
                    <td><code>{{ $notice->reference }}</code></td>
                    <td class="num">{{ Format::money($notice->amount) }}</td>
                    <td class="row-act">
                        @if ($isTreasurer)
                            <form method="POST" action="{{ route('paynotice.dismiss', $notice) }}" class="inline">
                                @csrf
                                <button class="btn sm" type="submit">Dismiss</button>
                            </form>
                            <button class="btn sm primary" data-open="dlg-pn-{{ $notice->id }}">Record</button>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if ($notices->isEmpty())
                <x-empty-row :cols="7" message="No open notices." />
            @endif
            </tbody>
        </table>
    </div>
</section>

<section class="stack">
    <x-sec title="Members' savings">
        @if ($isTreasurer)
            <x-slot:actions>
                <button class="btn primary sm" data-open="dlg-deposit"><x-icon name="plus" />Record deposit</button>
            </x-slot:actions>
        @endif
    </x-sec>
    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>Member</th>
                <th class="num">Pledge</th>
                <th class="num">Savings</th>
                <th class="num">Interest available</th>
                <th class="num">Months</th>
                <th>Last saving</th>
                <th>{{ $monthTag }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($savers as $row)
                @php $member = $row['member']; @endphp
                <tr class="click" data-open="dlg-member-{{ $member->id }}">
                    <td>
                        {{ $member->name }}
                        <span class="cell-sub">{{ $member->code }} · {{ $member->department }}</span>
                    </td>
                    <td class="num">{{ Format::money($member->monthly_pledge) }}</td>
                    <td class="num">{{ Format::money($row['balance']) }}</td>
                    <td class="num">{{ Format::money($row['interest']) }}</td>
                    <td class="num">{{ $row['months'] }}</td>
                    <td>{{ $row['last'] ? Format::date($row['last']) : 'None' }}</td>
                    <td>
                        @if ($row['saved'])
                            <x-pill tone="ok">Saved</x-pill>
                        @elseif ($row['checking'])
                            <x-pill tone="warn">Being checked</x-pill>
                        @else
                            <x-pill tone="muted">Not yet</x-pill>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <td>{{ $savers->count() }} active savers</td>
                <td class="num">{{ Format::money($savers->sum(fn (array $r) => (int) $r['member']->monthly_pledge)) }}</td>
                <td class="num">{{ Format::money($savers->sum('balance')) }}</td>
                <td class="num">{{ Format::money($savers->sum('interest')) }}</td>
                <td colspan="3"></td>
            </tr>
            </tfoot>
        </table>
    </div>
</section>
