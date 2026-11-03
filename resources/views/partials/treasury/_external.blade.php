{{-- Money the group borrowed from outside. It is the only way a month with more demand than savings
     gets paid, so it is listed with what is still owing and when the next instalment falls due. --}}
@php
    use App\Support\Format;
@endphp

<section class="stack">
    <x-sec title="External borrowing by PEG"
           sub="PEG can borrow from outside lenders to meet loan demand. Every facility needs the Chairperson and the Patron.">
        @if ($isTreasurer)
            <x-slot:actions>
                <button class="btn sm" data-open="dlg-extrepay"><x-icon name="out" />Repay a lender</button>
                <button class="btn sm primary" data-open="dlg-ext"><x-icon name="plus" />Record new borrowing</button>
            </x-slot:actions>
        @endif
    </x-sec>
    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>Facility</th>
                <th>Lender</th>
                <th>Received</th>
                <th class="num">Amount</th>
                <th>Terms</th>
                <th class="num">Principal repaid</th>
                <th class="num">Interest paid</th>
                <th class="num">Outstanding</th>
                <th>Next due</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($facilities as $row)
                @php
                    $facility = $row['loan'];
                    $info = $row['info'];
                    $booking = $row['booking'];
                @endphp
                <tr class="click" @if ($booking) data-href="{{ route('entries.show', $booking) }}" @endif>
                    <td class="mono">{{ $facility->ref }}</td>
                    <td>
                        {{ $facility->lender }}
                        <span class="cell-sub">{{ $facility->purpose }}</span>
                    </td>
                    <td>{{ $facility->received_on ? Format::date($facility->received_on->toDateString()) : 'Pending' }}</td>
                    <td class="num">{{ Format::money($facility->amount) }}</td>
                    <td>{{ Format::rate($facility->rate) }} a month · {{ $facility->months }} months</td>
                    <td class="num">{{ Format::money($info['principal_paid']) }}</td>
                    <td class="num">{{ Format::money($info['interest_paid']) }}</td>
                    <td class="num">{{ Format::money($info['outstanding']) }}</td>
                    <td>
                        @if ($info['next_due'])
                            {{ Format::date($info['next_due']) }}
                            <span class="cell-sub">{{ Format::ugx($info['next_principal'] + $info['next_interest']) }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($facility->status === \App\Models\ExternalLoan::ACTIVE)
                            <x-pill :tone="$info['outstanding'] > 0 ? 'ok' : 'muted'">
                                {{ $info['outstanding'] > 0 ? 'Running' : 'Repaid' }}
                            </x-pill>
                        @elseif ($facility->status === \App\Models\ExternalLoan::IN_APPROVAL)
                            @if ($booking)
                                <x-pill :tone="\App\Support\Trail::transactionTone($booking->status)">{{ $booking->statusLabel() }}</x-pill>
                            @endif
                        @else
                            <x-pill tone="bad">Rejected</x-pill>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if ($facilities->isEmpty())
                <x-empty-row :cols="10" message="PEG has not borrowed yet." />
            @endif
            </tbody>
        </table>
    </div>
</section>
