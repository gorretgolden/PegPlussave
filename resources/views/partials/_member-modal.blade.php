{{-- One member, seen from the desk that has to decide about them. The treasury half is only printed
     for the offices that may read it, which is why the same box serves the directory and the bank. --}}
@php
    use App\Support\Format;

    $isTreasury = auth()->user()->can('view-treasury');
    $isSecretary = auth()->user()->hasAnyRole(['secretary', 'admin']);
    $user = $member->user;

    $status = $member->status === 'left'
        ? 'Left on '.Format::date($member->exit_on?->toDateString())
        : ($member->exit_flagged ? 'Leaving PlusSave' : 'Active');

    $head = [
        ['Member no.', '<span class="mono">'.e($member->code).'</span>'],
        ['Department', e($member->department)],
        ['Extension', e($member->extension ?: 'None')],
        ['PEG role', e($user?->roleLabel() ?: 'Member')],
        ['Joined', Format::date($member->joined_on?->toDateString())],
        ['Status', e($status)],
    ];

    // A page that lists many members already holds the ledgers for all of them and passes them in;
    // only the single member box works them out itself.
    $ledgers = $loanLedgers ?? \App\Support\TreasuryPosition::loanLedgers();
    $running = $isTreasury
        ? collect($ledgers)->first(fn (array $ledger) => $ledger['loan']->member_id === $member->id
            && $ledger['loan']->disbursed_on && $ledger['balance'] > 0)
        : null;

    $treasury = $isTreasury ? [
        ['Monthly pledge', Format::ugx($member->monthly_pledge)],
        ['Principal savings', Format::ugx((int) ($row['balance'] ?? 0))],
        ['Interest available', Format::ugx(max(0, (int) ($row['interest'] ?? 0)))],
        ['Months saved', count($row['months'] ?? [])],
        ['Running loan', $running
            ? e($running['loan']->ref).' · '.Format::ugx($running['balance']).' · '.e($running['state'])
            : 'None'],
    ] : [];

    $actions = [];

    if ($isSecretary && $member->isActive()) {
        $actions[] = 'edit';
    }

    if (auth()->user()->hasRole('secretary') && $member->isActive()) {
        $actions[] = $member->exit_flagged ? 'unflag' : 'flag';
    }

    if ($isTreasury && $member->isActive()) {
        $actions[] = 'deposit';
        $actions[] = 'exit';
    }
@endphp

<div class="modal-back" id="dlg-member-{{ $member->id }}" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-member-{{ $member->id }}-h" data-nofocus>
        <div class="mdl-h">
            <h3 id="dlg-member-{{ $member->id }}-h">{{ $member->name }}</h3>
            <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
        </div>
        <div class="mdl-b">
            <x-kv :pairs="$head" />

            @if ($member->exit_flagged && $member->exit_note)
                <div class="warnbox">
                    <x-icon name="flag" />
                    <span>Exit notice: {{ $member->exit_note }}</span>
                </div>
            @endif

            @if ($isTreasury)
                <div class="fieldset">
                    <h4><x-icon name="lock" />Treasury view</h4>
                    <x-kv :pairs="$treasury" />
                </div>
            @endif

            @if ($actions !== [])
                <div class="form-actions">
                    @if (in_array('edit', $actions, true))
                        <button type="button" class="btn" data-open="dlg-editmember-{{ $member->id }}">Edit details</button>
                    @endif
                    @if (in_array('flag', $actions, true))
                        <button type="button" class="btn danger" data-open="dlg-exitnotice-{{ $member->id }}">Record exit notice</button>
                    @endif
                    @if (in_array('unflag', $actions, true))
                        <form method="POST" action="{{ route('directory.unflag', $member) }}" class="inline">
                            @csrf @method('DELETE')
                            <button class="btn" type="submit">Cancel exit notice</button>
                        </form>
                    @endif
                    @if (in_array('deposit', $actions, true))
                        <button type="button" class="btn" data-open="dlg-deposit" data-set-dep_member="{{ $member->id }}">Record deposit</button>
                    @endif
                    @if (in_array('exit', $actions, true))
                        <button type="button" class="btn primary" data-open="dlg-exit" data-set-exi_member="{{ $member->id }}">Exit settlement</button>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@if (auth()->user()->hasRole('secretary') && $member->isActive() && ! $member->exit_flagged)
    <div class="modal-back" id="dlg-exitnotice-{{ $member->id }}" hidden>
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-exitnotice-{{ $member->id }}-h">
            <div class="mdl-h">
                <h3 id="dlg-exitnotice-{{ $member->id }}-h">Exit notice · {{ $member->name }}</h3>
                <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
            </div>
            <div class="mdl-b">
                <form method="POST" action="{{ route('directory.exit', $member) }}" class="form" data-reset>
                    @csrf
                    <p class="muted small">Record that {{ $member->name }} is leaving PlusSave. The Treasurer then prepares the settlement of savings and interest, which the Chairperson and Patron approve.</p>
                    <div class="field">
                        <label for="en-n-{{ $member->id }}">Note for the Treasurer</label>
                        <input id="en-n-{{ $member->id }}" name="note" required placeholder="e.g. Resigned. Last day 30 Nov 2026.">
                        @error('note')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="en-d-{{ $member->id }}">Last day</label>
                        <input id="en-d-{{ $member->id }}" name="exit_on" type="date" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn" data-close-modal>Cancel</button>
                        <button class="btn primary" type="submit">Record exit notice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
