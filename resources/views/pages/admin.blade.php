{{-- Accounts, the rules and the mark on the menu. Nothing on this page touches a balance: the money
     is kept by the people whose desks carry it, and the owner of the system is the one account that
     cannot read it. --}}
@extends('layouts.peg')

@section('content')
    <x-tabs :options="$tabs" :current="$tab" label="System views" />

    @if ($tab === 'users')
        <div class="rules">
            <x-icon name="info" />
            <span>Each PlusSave staff member who joins PEG gets an account. Assign committee roles here; changes take effect at the next sign-in and are recorded in the audit trail.</span>
        </div>

        <div class="sec">
            <div></div>
            <div class="sec-r">
                <button class="btn sm primary" type="button" data-open="dlg-adduser"><x-icon name="plus" />Open an account</button>
            </div>
        </div>

        <div class="tbl">
            <table>
                <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>PEG role</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($accounts as $account)
                    @php
                        $member = $account->member;
                        $isOwner = $account->username === auth()->user()->username;
                        $closed = $account->isClosed();
                    @endphp
                    <tr>
                        <td>
                            <b>{{ $account->name }}</b>
                            <span class="cell-sub">{{ $member?->code ?? 'System account' }}</span>
                        </td>
                        <td class="mono">{{ $account->username }}</td>
                        <td>
                            @if ($account->hasRole('admin'))
                                System Owner
                            @elseif ($member || $isOwner)
                                <form method="POST" action="{{ route('admin.users.role', $account) }}" class="inline" data-submit>
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr" for="r-{{ $account->id }}">PEG role for {{ $account->name }}</label>
                                    <select id="r-{{ $account->id }}" name="role" class="btn sm" @disabled($closed)>
                                        @foreach ($roles as $key => $label)
                                            <option value="{{ $key }}" @selected($account->roleKey() === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <noscript><button class="btn sm" type="submit">Set</button></noscript>
                                </form>
                                @error('role')<div class="field-error">{{ $message }}</div>@enderror
                            @else
                                {{ $account->roleLabel() }}
                            @endif
                        </td>
                        <td>{{ $member?->department ?? 'IT' }}</td>
                        <td>
                            @if ($closed)
                                <x-pill tone="muted">Closed</x-pill>
                            @else
                                <x-pill tone="ok">Active</x-pill>
                            @endif
                        </td>
                        <td class="row-act">
                            @unless ($isOwner)
                                <button class="btn sm" type="button" data-open="dlg-password-{{ $account->id }}">Password</button>
                                <form method="POST" action="{{ route('admin.users.status', $account) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn sm" type="submit">{{ $closed ? 'Reopen' : 'Close' }}</button>
                                </form>
                            @else
                                <span class="muted small">You</span>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($tab === 'settings')
        <form method="POST" action="{{ route('admin.settings') }}" class="form panel">
            @csrf
            @method('PUT')

            <div class="fieldset">
                <h4>Savings</h4>
                <div class="grid3">
                    <div class="field">
                        <label for="st-min">Minimum monthly saving (UGX)</label>
                        <input id="st-min" name="min_monthly" type="number" min="0" step="1000"
                               value="{{ old('min_monthly', $policy['min_monthly']) }}">
                        @error('min_monthly')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="st-day">Contribution deadline (day of month)</label>
                        <input id="st-day" name="contribution_day" type="number" min="1" max="31"
                               value="{{ old('contribution_day', $policy['contribution_day']) }}">
                        @error('contribution_day')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="st-pm">Annual interest payout month</label>
                        <select id="st-pm" name="payout_month">
                            @foreach (range(1, 12) as $month)
                                <option value="{{ $month }}" @selected((int) old('payout_month', $policy['payout_month']) === $month)>
                                    {{ \Carbon\CarbonImmutable::create(null, $month, 1)->format('F') }}
                                </option>
                            @endforeach
                        </select>
                        @error('payout_month')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="st-ds">Share of net loan interest paid to savers (%)</label>
                        <input id="st-ds" name="distribution_share" type="number" min="0" max="100"
                               value="{{ old('distribution_share', $policy['distribution_share']) }}">
                        @error('distribution_share')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="st-rs">Liquidity reserve (% of savings)</label>
                        <input id="st-rs" name="reserve_percent" type="number" min="0" max="100"
                               value="{{ old('reserve_percent', $policy['reserve_percent']) }}">
                        @error('reserve_percent')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="fieldset">
                <h4>Loans</h4>
                <div class="grid3">
                    <div class="field">
                        <label for="st-rate">Interest rate (% a month)</label>
                        <input id="st-rate" name="loan_rate" type="number" min="0" step="0.1"
                               value="{{ old('loan_rate', $policy['loan_rate']) }}">
                        @error('loan_rate')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="st-meth">Interest method</label>
                        <select id="st-meth" name="loan_method">
                            <option value="flat" @selected(old('loan_method', $policy['loan_method']) === 'flat')>Flat on the amount</option>
                            <option value="reducing" @selected(old('loan_method', $policy['loan_method']) === 'reducing')>Reducing balance</option>
                        </select>
                        @error('loan_method')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="st-mx">Longest repayment period (months)</label>
                        <input id="st-mx" name="max_months" type="number" min="1" max="24"
                               value="{{ old('max_months', $policy['max_months']) }}">
                        @error('max_months')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="st-mul">Loan limit (times savings)</label>
                        <input id="st-mul" name="loan_multiple" type="number" min="1" step="0.5"
                               value="{{ old('loan_multiple', $policy['loan_multiple']) }}">
                        @error('loan_multiple')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="st-msm">Months saved before borrowing</label>
                        <input id="st-msm" name="min_saving_months" type="number" min="0"
                               value="{{ old('min_saving_months', $policy['min_saving_months']) }}">
                        @error('min_saving_months')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="st-thr">Patron authorises loans above (UGX)</label>
                        <input id="st-thr" name="patron_threshold" type="number" min="0" step="100000"
                               value="{{ old('patron_threshold', $policy['patron_threshold']) }}">
                        @error('patron_threshold')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="lbl-s">Repayment period by amount</div>
                <div class="grid3">
                    <div class="field">
                        <label for="b1u">Up to (UGX)</label>
                        <input id="b1u" name="band_1_up_to" type="number" min="0" step="100000"
                               value="{{ old('band_1_up_to', $policy['tenure_bands'][0]['upTo']) }}">
                        @error('band_1_up_to')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="b1m">Months</label>
                        <input id="b1m" name="band_1_months" type="number" min="1" max="24"
                               value="{{ old('band_1_months', $policy['tenure_bands'][0]['months']) }}">
                        @error('band_1_months')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <span></span>
                    <div class="field">
                        <label for="b2u">Up to (UGX)</label>
                        <input id="b2u" name="band_2_up_to" type="number" min="0" step="100000"
                               value="{{ old('band_2_up_to', $policy['tenure_bands'][1]['upTo']) }}">
                        @error('band_2_up_to')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="b2m">Months</label>
                        <input id="b2m" name="band_2_months" type="number" min="1" max="24"
                               value="{{ old('band_2_months', $policy['tenure_bands'][1]['months']) }}">
                        @error('band_2_months')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <span></span>
                    <div class="field">
                        <label for="b3u">Above that</label>
                        <input id="b3u" value="Any larger amount" readonly>
                    </div>
                    <div class="field">
                        <label for="b3m">Months</label>
                        <input id="b3m" name="band_3_months" type="number" min="1" max="24"
                               value="{{ old('band_3_months', $policy['tenure_bands'][2]['months']) }}">
                        @error('band_3_months')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="maker-note">
                <x-icon name="info" />
                <span>New settings apply to new entries. Loans already booked keep the rate and period they were approved on.</span>
            </div>

            <div class="form-actions">
                <button class="btn primary" type="submit">Save settings</button>
            </div>
        </form>
    @else
        <section class="panel stack">
            <x-sec title="PlusSave logo" sub="Shown at the top of every menu and on the sign-in page. Upload a PNG or SVG under 600 KB." />

            <div class="brand-prev">
                <div class="bp-dark">
                    @if ($logo)
                        <div class="logo-img"><img src="{{ $logo }}" alt="PlusSave Financial Solutions"></div>
                    @else
                        <div class="wordmark"><span class="wm-1">Plus<b>Save</b></span><span class="wm-2">Financial Solutions</span></div>
                    @endif
                </div>
                <div class="bp-light">
                    @if ($logo)
                        <div class="logo-img"><img src="{{ $logo }}" alt="PlusSave Financial Solutions"></div>
                    @else
                        <div class="wordmark" style="color: var(--ink)"><span class="wm-1">Plus<b>Save</b></span><span class="wm-2" style="color: var(--gold)">Financial Solutions</span></div>
                    @endif
                </div>
            </div>

            <div class="sec-r">
                <form method="POST" action="{{ route('admin.logo') }}" enctype="multipart/form-data" class="form inline" data-submit>
                    @csrf
                    <label class="btn primary" for="logo-up"><x-icon name="upload" />Upload logo</label>
                    <input id="logo-up" name="logo" type="file" accept="image/png,image/svg+xml,image/jpeg,image/webp" class="sr">
                    <noscript><button class="btn sm" type="submit">Upload</button></noscript>
                    @error('logo')<div class="field-error">{{ $message }}</div>@enderror
                </form>

                @if ($logo)
                    <form method="POST" action="{{ route('admin.logo.clear') }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button class="btn" type="submit">Use the text wordmark</button>
                    </form>
                @endif
            </div>
        </section>
    @endif
@endsection

@section('modals')
    @if ($tab === 'users')
        <div class="modal-back" id="dlg-adduser" hidden>
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-adduser-h">
                <div class="mdl-h">
                    <h3 id="dlg-adduser-h">Open an account</h3>
                    <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
                </div>
                <div class="mdl-b">
                    <form method="POST" action="{{ route('admin.users.store') }}" class="form" data-reset>
                        @csrf
                        <p class="muted small">A member who joins through the register gets an account by itself. Use this for a committee office that is not on the register, or to make one by hand.</p>

                        <div class="grid2">
                            <div class="field">
                                <label for="au-n">Name</label>
                                <input id="au-n" name="name" required maxlength="120" value="{{ old('name') }}">
                                @error('name')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label for="au-u">Sign-in name</label>
                                <input id="au-u" name="username" required maxlength="40" value="{{ old('username') }}">
                                @error('username')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label for="au-r">PEG role</label>
                                <select id="au-r" name="role">
                                    @foreach ($roles as $key => $label)
                                        <option value="{{ $key }}" @selected(old('role', 'member') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('role')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label for="au-m">Member (optional)</label>
                                <select id="au-m" name="member_id">
                                    <option value="">Not a member</option>
                                    @foreach ($members as $member)
                                        <option value="{{ $member->id }}" @selected((int) old('member_id') === $member->id)>
                                            {{ $member->name }} &middot; {{ $member->code }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('member_id')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label for="au-p">Password</label>
                                <input id="au-p" name="password" type="text" required minlength="8" value="{{ old('password') }}">
                                @error('password')<div class="field-error">{{ $message }}</div>@enderror
                                <span class="hint">Tell the person this password and have them change it after signing in.</span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn" data-close-modal>Cancel</button>
                            <button class="btn primary" type="submit">Open account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @foreach ($accounts as $account)
            <div class="modal-back" id="dlg-password-{{ $account->id }}" hidden>
                <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-password-{{ $account->id }}-h">
                    <div class="mdl-h">
                        <h3 id="dlg-password-{{ $account->id }}-h">Password for {{ $account->name }}</h3>
                        <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
                    </div>
                    <div class="mdl-b">
                        <form method="POST" action="{{ route('admin.users.password', $account) }}" class="form" data-reset>
                            @csrf
                            <p class="muted small">This replaces the password they are using now. Nothing is sent to them by the system, so the new one has to be passed on by word.</p>

                            <div class="grid2">
                                <div class="field">
                                    <label for="pw-n-{{ $account->id }}">New password</label>
                                    <input id="pw-n-{{ $account->id }}" name="password" type="text" required minlength="8">
                                    @error('password')<div class="field-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field">
                                    <label for="pw-c-{{ $account->id }}">Type it again</label>
                                    <input id="pw-c-{{ $account->id }}" name="password_confirmation" type="text" required minlength="8">
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn" data-close-modal>Cancel</button>
                                <button class="btn primary" type="submit">Set password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
