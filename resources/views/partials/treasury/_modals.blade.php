{{-- The eight boxes a Treasurer works from. Each one is the entry it makes: the words say who checks
     it next, and the figures under the boxes are worked out by the same services that will write it. --}}
@php
    use App\Models\Transaction;
    use App\Support\Format;
    use App\Support\Policy;

    $minMonthly = (int) Policy::get('min_monthly');
    $today = now()->toDateString();
    $thisMonth = now()->format('Y-m');
    $reasons = ['Resigned from PlusSave', 'Contract ended', 'Retired', 'Transferred to another employer', 'Other'];

    $leaving = $modals['members']->sortBy([
        [fn ($member) => $member->exit_flagged ? 0 : 1, 'asc'],
        ['code', 'asc'],
    ]);
@endphp

{{-- A saving the member did not report, or reported and the Treasurer is confirming. --}}
<div class="modal-back" id="dlg-deposit" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-deposit-h">
        <div class="mdl-h"><h3 id="dlg-deposit-h">Record a savings deposit</h3><button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button></div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('treasury.deposit') }}" class="form" data-reset>
                @csrf
                <div class="field">
                    <label for="d-m">Member</label>
                    <select id="d-m" name="dep_member">
                        @foreach ($modals['members'] as $member)
                            <option value="{{ $member->id }}">{{ $member->code }} · {{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid2">
                    <div class="field">
                        <label for="d-a">Amount (UGX)</label>
                        <input id="d-a" name="dep_amount" type="number" min="{{ $minMonthly }}" step="1000" required
                               placeholder="At least {{ Format::money($minMonthly) }}">
                        @error('dep_amount')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="d-mo">Month covered</label>
                        <input id="d-mo" name="dep_month" type="month" required value="{{ $thisMonth }}">
                    </div>
                    <div class="field">
                        <label for="d-d">Value date</label>
                        <input id="d-d" name="dep_date" type="date" required value="{{ $today }}">
                    </div>
                    <div class="field">
                        <label for="d-c">Channel</label>
                        <select id="d-c" name="dep_channel">
                            @foreach (Transaction::CHANNELS as $channel)
                                <option>{{ $channel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="d-r">Reference</label>
                    <input id="d-r" name="dep_ref" placeholder="Mobile money or bank reference">
                </div>
                <x-maker-note />
                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn primary" type="submit">Submit for checking</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- The group lending to one of its own, entered by the office rather than requested by the member. --}}
<div class="modal-back" id="dlg-loanbook-t" hidden>
    <div class="modal wide" role="dialog" aria-modal="true" aria-labelledby="dlg-loanbook-t-h">
        <div class="mdl-h"><h3 id="dlg-loanbook-t-h">Book a loan for a member</h3><button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button></div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('treasury.loan.book') }}" class="form" data-reset data-live="loan"
                  data-scope="t" data-live-amount="lb_amount" data-live-months="lb_months"
                  data-policy="{{ json_encode($loanPolicy) }}" data-facts="{{ json_encode($facts) }}">
                @csrf
                <div class="field">
                    <label for="tlb-m">Member</label>
                    <select id="tlb-m" name="lb_member">
                        @foreach ($modals['members'] as $member)
                            <option value="{{ $member->id }}">{{ $member->code }} · {{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid2">
                    <div class="field">
                        <label for="tlb-a">Amount (UGX)</label>
                        <input id="tlb-a" name="lb_amount" type="number" min="{{ \App\Http\Controllers\MemberLoanController::MINIMUM }}" step="10000" inputmode="numeric" required>
                        @error('lb_amount')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="tlb-n">Repayment period</label>
                        <select id="tlb-n" name="lb_months" data-max="0">
                            @foreach (range(1, (int) $loanPolicy['maxMonths']) as $months)
                                <option value="{{ $months }}">{{ $months }} {{ $months > 1 ? 'months' : 'month' }}</option>
                            @endforeach
                        </select>
                        <span class="hint">{{ Policy::tenureBandsText() }}</span>
                    </div>
                </div>
                <div class="grid2">
                    <div class="field">
                        <label for="tlb-p">Purpose</label>
                        <select id="tlb-p" name="lb_purpose">
                            @foreach (Transaction::PURPOSES as $purpose)
                                <option>{{ $purpose }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="tlb-d">Details (optional)</label>
                        <input id="tlb-d" name="lb_detail" placeholder="e.g. Term 1 fees">
                    </div>
                </div>
                <div class="field">
                    <label for="tlb-note">Treasurer's note</label>
                    <textarea id="tlb-note" name="lb_note" placeholder="Required when the loan is outside policy. It then goes to the Patron."></textarea>
                    @error('lb_note')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="loan-preview"></div>
                <x-maker-note />
                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn primary" type="submit">Submit for checking</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Money coming back against a loan that is running. --}}
<div class="modal-back" id="dlg-repay" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-repay-h">
        <div class="mdl-h"><h3 id="dlg-repay-h">Record a loan repayment</h3><button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button></div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('treasury.repay') }}" class="form" data-reset data-live="repay"
                  data-figures="{{ route('treasury.repay-figures') }}">
                @csrf
                <div class="field">
                    <label for="r-l">Loan</label>
                    <select id="r-l" name="rp_loan">
                        @foreach ($modals['running'] as $ledger)
                            <option value="{{ $ledger['loan']->id }}">
                                {{ $ledger['loan']->ref }} · {{ $ledger['loan']->member?->name }} · balance {{ Format::money($ledger['balance']) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="repay-info"></div>
                <div class="grid2">
                    <div class="field">
                        <label for="r-a">Amount received (UGX)</label>
                        <input id="r-a" name="rp_amount" type="number" min="1" step="100" required>
                        @error('rp_amount')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="r-d">Value date</label>
                        <input id="r-d" name="rp_date" type="date" required value="{{ $today }}">
                    </div>
                    <div class="field">
                        <label for="r-c">Channel</label>
                        <select id="r-c" name="rp_channel">
                            @foreach (Transaction::CHANNELS as $channel)
                                <option>{{ $channel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="r-r">Reference</label>
                        <input id="r-r" name="rp_ref" placeholder="Reference">
                    </div>
                </div>
                <x-maker-note extra="Interest is settled first, then principal." />
                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn primary" type="submit">Submit for checking</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Sharing the month's interest. The pool is the Treasurer's decision; everything under it is the
     arithmetic that follows from it. --}}
<div class="modal-back" id="dlg-interest" hidden>
    <div class="modal wide" role="dialog" aria-modal="true" aria-labelledby="dlg-interest-h">
        <div class="mdl-h"><h3 id="dlg-interest-h">Post monthly interest to savers</h3><button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button></div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('treasury.interest') }}" class="form" data-reset data-live="interest"
                  data-figures="{{ route('treasury.figures') }}">
                @csrf
                <div class="grid2">
                    <div class="field">
                        <label for="i-m">Month</label>
                        <input id="i-m" name="in_month" type="month" required value="{{ $pendingMonth }}">
                        @error('in_month')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="i-p">Pool to share (UGX)</label>
                        <input id="i-p" name="in_pool" type="number" min="0" step="1">
                        <span class="hint">Suggested: {{ (int) Policy::get('distribution_share') }}% of net loan interest. You can adjust it.</span>
                        @error('in_pool')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="int-calc"></div>
                <div class="int-alloc"></div>
                <x-maker-note />
                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn primary" type="submit">Submit for checking</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- The group taking a debt on behalf of everybody, which is why it never stops at the Chairperson. --}}
<div class="modal-back" id="dlg-ext" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-ext-h">
        <div class="mdl-h"><h3 id="dlg-ext-h">Record external borrowing</h3><button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button></div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('treasury.external') }}" class="form" data-reset data-live="ext">
                @csrf
                <div class="field">
                    <label for="e-l">Lender</label>
                    <input id="e-l" name="ex_lender" required placeholder="e.g. a bank or the staff welfare facility">
                    @error('ex_lender')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="grid3">
                    <div class="field">
                        <label for="e-a">Amount (UGX)</label>
                        <input id="e-a" name="ex_amount" type="number" min="1" step="10000" required value="2000000">
                    </div>
                    <div class="field">
                        <label for="e-r">Rate (% a month, flat)</label>
                        <input id="e-r" name="ex_rate" type="number" min="0" step="0.1" value="1.5">
                    </div>
                    <div class="field">
                        <label for="e-n">Months</label>
                        <input id="e-n" name="ex_months" type="number" min="1" max="36" value="6">
                    </div>
                </div>
                <div class="grid2">
                    <div class="field">
                        <label for="e-d">Expected receipt date</label>
                        <input id="e-d" name="ex_date" type="date" value="{{ $today }}">
                    </div>
                    <div class="field">
                        <label for="e-p">Purpose</label>
                        <input id="e-p" name="ex_purpose" placeholder="e.g. Meet December loan demand">
                    </div>
                </div>
                <div class="ext-calc"></div>
                <x-maker-note extra="External borrowing always goes on to the Patron." />
                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn primary" type="submit">Submit for checking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-back" id="dlg-extrepay" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-extrepay-h">
        <div class="mdl-h"><h3 id="dlg-extrepay-h">Repay an external lender</h3><button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button></div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('treasury.external.repay') }}" class="form" data-reset data-live="extrepay">
                @csrf
                <div class="field">
                    <label for="x-e">Facility</label>
                    <select id="x-e" name="xr_loan">
                        @foreach ($modals['lenders'] as $facility)
                            @php $info = $facility->info(); @endphp
                            <option value="{{ $facility->id }}"
                                    data-principal="{{ $info['next_principal'] }}"
                                    data-interest="{{ $info['next_interest'] }}">
                                {{ $facility->ref }} · {{ $facility->lender }} · outstanding {{ Format::money($info['outstanding']) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid2">
                    <div class="field">
                        <label for="x-p">Principal (UGX)</label>
                        <input id="x-p" name="xr_principal" type="number" min="0">
                        @error('xr_principal')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="x-i">Interest (UGX)</label>
                        <input id="x-i" name="xr_interest" type="number" min="0">
                    </div>
                    <div class="field">
                        <label for="x-d">Value date</label>
                        <input id="x-d" name="xr_date" type="date" value="{{ $today }}">
                    </div>
                    <div class="field">
                        <label for="x-r">Reference</label>
                        <input id="x-r" name="xr_ref">
                    </div>
                </div>
                <x-maker-note />
                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn primary" type="submit">Submit for checking</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Once a year the accumulated interest leaves the box and goes to the members who earned it. --}}
@php $payout = $modals['payout']; @endphp
<div class="modal-back" id="dlg-payout" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-payout-h">
        <div class="mdl-h"><h3 id="dlg-payout-h">Annual interest payout {{ $payout['year'] }}</h3><button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button></div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('treasury.payout') }}" class="form">
                @csrf
                <input type="hidden" name="pa_year" value="{{ $payout['year'] }}">
                @unless ($payout['inWindow'])
                    <div class="warnbox">
                        <x-icon name="alert" />
                        <span>The payout month is {{ Format::monthName($payout['year'].'-'.str_pad((string) $payout['month'], 2, '0', STR_PAD_LEFT)) }}. You can prepare it early; the Patron decides.</span>
                    </div>
                @endunless
                <div class="rules">
                    <x-icon name="info" />
                    <span>Members withdraw their accumulated interest once a year. Principal savings stay in PEG. Post any outstanding monthly interest first so it is included.</span>
                </div>
                @php
                    $total = array_sum(array_column($payout['allocations'], 'amount'));
                    $pairs = [
                        ['Savers to pay', count($payout['allocations'])],
                        ['Total interest', '<b>'.Format::ugx($total).'</b>'],
                        ['Approvals', 'Chairperson, then Patron'],
                    ];
                @endphp
                <x-kv :pairs="$pairs" />
                @if ($payout['allocations'] !== [])
                    @include('partials.treasury._allocations', ['allocations' => $payout['allocations'], 'heading' => 'Show each saver'])
                @endif
                <x-maker-note extra="The annual payout always goes on to the Patron." />
                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn primary" type="submit">Submit for checking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-back" id="dlg-exit" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-exit-h">
        <div class="mdl-h"><h3 id="dlg-exit-h">Exit settlement</h3><button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button></div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('treasury.exit') }}" class="form" data-reset data-live="exit"
                  data-figures="{{ route('treasury.exit-figures') }}">
                @csrf
                <div class="field">
                    <label for="ex-m">Member leaving PlusSave</label>
                    <select id="ex-m" name="exi_member">
                        @foreach ($leaving as $member)
                            <option value="{{ $member->id }}" @selected($member->exit_flagged)>
                                {{ $member->code }} · {{ $member->name }}{{ $member->exit_flagged ? ' · exit notice' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="exit-calc"></div>
                <div class="grid2">
                    <div class="field">
                        <label for="ex-d">Exit date</label>
                        <input id="ex-d" name="exi_date" type="date" value="{{ $today }}">
                    </div>
                    <div class="field">
                        <label for="ex-r">Reason</label>
                        <select id="ex-r" name="exi_reason">
                            @foreach ($reasons as $reason)
                                <option>{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <x-maker-note extra="Exit settlements always go on to the Patron." />
                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn primary" type="submit">Submit for checking</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/peg-live.js') }}"></script>
@endpush
