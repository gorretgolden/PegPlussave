{{-- The appraisal is the moment the group decides. The figures the member is judged on are printed at
     the top, the terms can still be changed here, and declining requires saying why in words. --}}
@php
    use App\Support\Format;
    use App\Support\LoanFacts;
    use App\Support\Policy;
    use App\Support\TreasuryPosition;

    $memberRow = TreasuryPosition::memberBalances()[$loan->member_id] ?? ['balance' => 0, 'months' => []];
    $balance = (int) $memberRow['balance'];
    $monthsSaved = count($memberRow['months']);
    $guarantors = $loan->guarantors ?? collect();

    $head = [
        ['Member', e($loan->member?->name).' <span class="muted">· '.e($loan->member?->code).' · '.e($loan->member?->department).'</span>'],
        ['Requested', Format::dateTime($loan->requested_at?->toDateTimeString())],
        ['Savings', Format::ugx($balance)],
        ['Months saved', $monthsSaved],
        ['Purpose', e($loan->purpose)],
        ['Guarantors', $guarantors->count() ? e($guarantors->pluck('name')->implode(', ')) : 'None'],
    ];

    $preview = LoanFacts::for([$loan->member_id], $loan->id);
    $ceiling = (int) Policy::get('max_months');
@endphp

<div class="modal-back" id="dlg-appraise-{{ $loan->id }}" hidden>
    <div class="modal wide" role="dialog" aria-modal="true" aria-labelledby="dlg-appraise-{{ $loan->id }}-h">
        <div class="mdl-h">
            <h3 id="dlg-appraise-{{ $loan->id }}-h">Loan request {{ $loan->ref }}</h3>
            <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
        </div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('treasury.loan.appraise', $loan) }}" class="form"
                  data-live="loan" data-scope="a" data-loan="{{ $loan->id }}" data-member="{{ $loan->member_id }}"
                  data-rate="{{ $loan->rate }}" data-method="{{ $loan->method }}"
                  data-live-amount="ap_amount" data-live-months="ap_months"
                  data-policy="{{ json_encode(\App\Support\LoanFacts::policy(TreasuryPosition::available())) }}"
                  data-facts="{{ json_encode($preview) }}">
                @csrf
                <x-kv :pairs="$head" />
                <div class="grid2">
                    <div class="field">
                        <label for="ap-a-{{ $loan->id }}">Amount (UGX)</label>
                        <input id="ap-a-{{ $loan->id }}" name="ap_amount" type="number" min="{{ \App\Http\Controllers\MemberLoanController::MINIMUM }}"
                               step="10000" required value="{{ $loan->amount }}">
                        @error('ap_amount')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="ap-n-{{ $loan->id }}">Repayment period</label>
                        <select id="ap-n-{{ $loan->id }}" name="ap_months" data-max="0">
                            @foreach (range(1, $ceiling) as $months)
                                <option value="{{ $months }}" @selected($months === (int) $loan->months)>{{ $months }} {{ $months > 1 ? 'months' : 'month' }}</option>
                            @endforeach
                        </select>
                        <span class="hint">{{ Policy::tenureBandsText() }}</span>
                    </div>
                </div>
                <div class="loan-preview"></div>
                <div class="field">
                    <label for="ap-note-{{ $loan->id }}">Appraisal note</label>
                    <textarea id="ap-note-{{ $loan->id }}" name="ap_note" placeholder="Your recommendation. Required to decline, or to submit a loan outside policy."></textarea>
                    @error('ap_note')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <x-maker-note />
                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn danger" type="submit" name="ap_decision" value="decline">Decline request</button>
                    <button class="btn primary" type="submit" name="ap_decision" value="submit">Submit for checking</button>
                </div>
            </form>
        </div>
    </div>
</div>
