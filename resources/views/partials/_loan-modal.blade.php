{{-- Everything one loan is made of, in the box a table row opens. The trail at the bottom is the
     record of who laid hands on it, drawn from the check rows themselves. --}}
@php
    $loan = $ledger['loan'];
    $member = $loan->member;
    $state = \App\Support\Trail::loanState($loan, $ledger);
    $booking = $loan->booking()->first();
    $isTreasury = ! empty($treasuryActions) && auth()->user()->can('view-treasury');
    $repayments = \App\Models\Transaction::query()
        ->where('loan_id', $loan->id)
        ->where('type', \App\Models\Transaction::REPAYMENT)
        ->orderByDesc('value_date')
        ->get();
@endphp
<div class="modal-back" id="dlg-loan-{{ $loan->id }}" hidden>
    <div class="modal wide" role="dialog" aria-modal="true" aria-labelledby="dlg-loan-{{ $loan->id }}-h">
        <div class="mdl-h">
            <h3 id="dlg-loan-{{ $loan->id }}-h">Loan {{ $loan->ref }}</h3>
            <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
        </div>
        <div class="mdl-b">
            @php
                $facts = [
                    ['Borrower', e($member?->name).' <span class="muted">· '.e($member?->code).'</span>'],
                    ['Amount', \App\Support\Format::ugx($loan->amount)],
                    ['Terms', $loan->months.' months · '.\App\Support\Format::rate($loan->rate).' a month '.e($loan->method)],
                    ['Purpose', e($loan->purpose)],
                    ['Requested', \App\Support\Format::date($loan->requested_at?->toDateString())],
                    ['Disbursed', $loan->disbursed_on ? \App\Support\Format::date($loan->disbursed_on->toDateString()) : 'Not yet'],
                    ['Status', '<span class="pill '.\App\Support\Trail::loanTone($state).'">'.$state.'</span>'],
                    $loan->disbursed_on ? ['Balance', \App\Support\Format::ugx($ledger['balance'])] : null,
                    $ledger['arrears'] > 0 ? ['In arrears', '<span class="neg">'.\App\Support\Format::ugx($ledger['arrears']).' · '.$ledger['daysLate'].' days</span>'] : null,
                ];
            @endphp
            <x-kv :pairs="$facts" />

            <div>
                <div class="lbl-s" style="margin-bottom:8px">Approval trail</div>
                <x-trail :steps="\App\Support\Trail::forLoan($loan, $booking)" />
            </div>

            @if ($loan->status === \App\Models\Loan::DECLINED && $loan->appraisal_note)
                <div class="badbox"><x-icon name="x" /><span>Declined by the Treasurer: {{ $loan->appraisal_note }}</span></div>
            @endif

            @if ($ledger['schedule'] !== [])
                @include('partials._schedule-table', ['ledger' => $ledger])
            @else
                @php
                    $preview = \App\Services\LoanSchedule::build((int) $loan->amount, (float) $loan->rate, (int) $loan->months, $loan->method, now()->toDateString());
                @endphp
                <div class="maker-note">
                    <x-icon name="info" />
                    <span>Once disbursed: {{ $loan->months }} installments of
                        {{ \App\Support\Format::ugx($preview[0]['total'] ?? 0) }}, total interest
                        {{ \App\Support\Format::ugx(array_sum(array_column($preview, 'interest'))) }}.</span>
                </div>
            @endif

            @if ($repayments->isNotEmpty() && $isTreasury)
                <div class="tbl fit">
                    <table>
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Ref</th>
                            <th class="num">Amount</th>
                            <th class="num">Interest</th>
                            <th class="num">Principal</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($repayments as $repayment)
                            @php $split = $ledger['splits'][$repayment->ref] ?? null; @endphp
                            <tr>
                                <td>{{ \App\Support\Format::date($repayment->value_date->toDateString()) }}</td>
                                <td class="mono">{{ $repayment->ref }}</td>
                                <td class="num">{{ \App\Support\Format::money($repayment->amount) }}</td>
                                <td class="num">{{ $split ? \App\Support\Format::money($split['interest']) : '' }}</td>
                                <td class="num">{{ $split ? \App\Support\Format::money($split['principal']) : '' }}</td>
                                <td><x-pill :tone="\App\Support\Trail::transactionTone($repayment->status)">{{ $repayment->statusLabel() }}</x-pill></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($isTreasury && $loan->disbursed_on && $ledger['balance'] > 0)
                <div class="form-actions">
                    <button class="btn primary" type="button" data-open="dlg-repay" data-set-rp_loan="{{ $loan->id }}">
                        <x-icon name="check" />Record repayment
                    </button>
                </div>
            @endif

            @if (auth()->user()->hasRole('treasurer') && $loan->status === \App\Models\Loan::REQUESTED)
                <div class="form-actions">
                    <button class="btn primary" type="button" data-open="dlg-appraise-{{ $loan->id }}">Appraise</button>
                </div>
            @endif
        </div>
    </div>
</div>
