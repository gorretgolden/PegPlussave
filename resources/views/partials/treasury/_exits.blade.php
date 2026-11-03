{{-- A member leaving takes their principal and the interest still sitting with them, less anything a
     loan of theirs still owes. The set-off is shown before the settlement is booked, never after. --}}
@php
    use App\Support\Format;
@endphp

<div class="rules">
    <x-icon name="info" />
    <span>Members only withdraw their principal when they leave PlusSave. The settlement pays principal
        plus accumulated interest, less any loan balance, and needs the Chairperson and the Patron.</span>
    @if ($isTreasurer)
        <button class="btn sm primary" data-open="dlg-exit"><x-icon name="flag" />Exit settlement</button>
    @endif
</div>

<section class="stack">
    <x-sec title="Exit notices from the Secretary" />
    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>Member</th>
                <th>Note</th>
                <th class="num">Savings</th>
                <th class="num">Interest</th>
                <th class="num">Loan balance</th>
                <th class="num">Net payable</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($leaving as $row)
                @php $settlement = $row['settlement']; @endphp
                <tr>
                    <td>
                        {{ $row['member']->name }}
                        <span class="cell-sub">{{ $row['member']->code }} · {{ $row['member']->department }}</span>
                    </td>
                    <td>{{ $row['member']->exit_note }}</td>
                    <td class="num">{{ Format::money($settlement['savings']) }}</td>
                    <td class="num">{{ Format::money($settlement['interest']) }}</td>
                    <td class="num">{{ Format::money($settlement['offset']) }}</td>
                    <td class="num"><b>{{ Format::money($settlement['net']) }}</b></td>
                    <td class="row-act">
                        @if ($row['pending'])
                            <x-pill tone="warn">In approval</x-pill>
                        @elseif ($isTreasurer)
                            <button class="btn sm primary" data-open="dlg-exit" data-set-member="{{ $row['member']->id }}">
                                Prepare settlement
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if ($leaving->isEmpty())
                <x-empty-row :cols="7" message="No exit notices." />
            @endif
            </tbody>
        </table>
    </div>
</section>

<section class="stack">
    <x-sec title="Settlements" />
    @include('partials._txn-table', ['entries' => $settlements])
</section>
