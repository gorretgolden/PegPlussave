{{-- The two things a member does for themselves. They are offered from the dashboard and carried on
     every screen a member can reach, because the notice is sent the day the salary is cut, not the
     day somebody remembers the right page. --}}
@if (auth()->user()->member_id)
    @php
        $me = auth()->user()->member_id;
        $pledge = \App\Models\Member::query()->find($me)?->monthly_pledge
            ?? \App\Support\Policy::get('min_monthly');
    @endphp

    <div class="modal-back" id="dlg-paynotice" hidden>
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-paynotice-h">
            <div class="mdl-h"><h3 id="dlg-paynotice-h">Notify a payment</h3><button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button></div>
            <div class="mdl-b">
                <form method="POST" action="{{ route('paynotice.store') }}" class="form" data-reset>
                    @csrf
                    <p class="muted small">Paid by mobile money or at the bank? Tell the Treasurer so it can be recorded and checked. It shows in your statement once the Chairperson checks it.</p>
                    <div class="grid2">
                        <div class="field">
                            <label for="p-a">Amount (UGX)</label>
                            <input id="p-a" name="pn_amount" type="number" min="{{ \App\Support\Policy::get('min_monthly') }}" step="1000" required value="{{ old('pn_amount', $pledge) }}">
                            @error('pn_amount')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="p-m">Month</label>
                            <input id="p-m" name="pn_month" type="month" required value="{{ old('pn_month', now()->format('Y-m')) }}">
                            @error('pn_month')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="p-d">Date paid</label>
                            <input id="p-d" name="pn_paid_on" type="date" required value="{{ old('pn_paid_on', now()->toDateString()) }}">
                            @error('pn_paid_on')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="p-c">Channel</label>
                            <select id="p-c" name="pn_channel">
                                @foreach (array_diff(\App\Models\Transaction::CHANNELS, ['Salary deduction']) as $channel)
                                    <option @selected(old('pn_channel') === $channel)>{{ $channel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label for="p-r">Transaction reference</label>
                        <input id="p-r" name="pn_reference" required placeholder="e.g. MM-58213" value="{{ old('pn_reference') }}">
                        @error('pn_reference')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn" data-close-modal>Cancel</button>
                        <button class="btn primary" type="submit">Send to Treasurer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-back" id="dlg-loanbook" hidden>
        @php
            $bookFacts = \App\Support\LoanFacts::for([$me]);
            $bookPolicy = \App\Support\LoanFacts::policy();
            $bookers = \App\Models\Member::query()->active()->orderBy('name')->get(['id', 'name'])
                ->reject(fn ($member) => $member->id === $me);
        @endphp
        <div class="modal wide" role="dialog" aria-modal="true" aria-labelledby="dlg-loanbook-h">
            <div class="mdl-h"><h3 id="dlg-loanbook-h">Book a loan</h3><button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button></div>
            <div class="mdl-b">
                <form method="POST" action="{{ route('member-loan.book') }}" class="form" data-reset data-live="loan"
                      data-scope="m" data-member="{{ $me }}"
                      data-policy="{{ json_encode($bookPolicy) }}" data-facts="{{ json_encode($bookFacts) }}">
                    @csrf
                    <p class="muted small">Your request goes to the Treasurer for appraisal and then the Chairperson. Loans above {{ \App\Support\Format::ugx($bookPolicy['patronThreshold']) }} also need the Patron. Only the treasury team sees it.</p>
                    <div class="grid2">
                        <div class="field">
                            <label for="lb-a">Amount (UGX)</label>
                            <input id="lb-a" name="amount" type="number" min="{{ \App\Http\Controllers\MemberLoanController::MINIMUM }}" step="10000" inputmode="numeric"
                                   required value="{{ old('amount', 1000000) }}">
                            @error('amount')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="lb-n">Repayment period</label>
                            <select id="lb-n" name="months" data-max="0">
                                @foreach (range(1, (int) $bookPolicy['maxMonths']) as $months)
                                    <option value="{{ $months }}" @selected((int) old('months', 3) === $months)>{{ $months }} {{ $months > 1 ? 'months' : 'month' }}</option>
                                @endforeach
                            </select>
                            <span class="hint">{{ \App\Support\Policy::tenureBandsText() }}</span>
                        </div>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label for="lb-p">Purpose</label>
                            <select id="lb-p" name="purpose">
                                @foreach (\App\Models\Transaction::PURPOSES as $purpose)
                                    <option @selected(old('purpose') === $purpose)>{{ $purpose }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="lb-d">Details (optional)</label>
                            <input id="lb-d" name="detail" placeholder="e.g. Term 1 fees" value="{{ old('detail') }}">
                        </div>
                    </div>
                    <div class="grid2">
                        @foreach ([1, 2] as $slot)
                            <div class="field">
                                <label for="lb-g{{ $slot }}">{{ $slot === 1 ? 'Guarantor' : 'Second guarantor' }} (optional)</label>
                                <select id="lb-g{{ $slot }}" name="guarantor_{{ $slot }}">
                                    <option value="">None</option>
                                    @foreach ($bookers as $member)
                                        <option value="{{ $member->id }}" @selected((int) old('guarantor_'.$slot) === $member->id)>{{ $member->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                    <div class="loan-preview"></div>
                    <x-maker-note extra="Your savings, how long you have saved and any running loan are checked first." />
                    <div class="form-actions">
                        <button type="button" class="btn" data-close-modal>Cancel</button>
                        <button class="btn primary" type="submit">Send request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('js/peg-live.js') }}"></script>
    @endpush
@endif
