{{-- What the entry actually says, in the words the officer has to check. Each type shows the figures
     that were decided when it was typed, not a summary of them: a Chairperson approving a loan needs
     the installment and the interest, and one approving a posting needs the shares. --}}
@php
    $meta = $transaction->meta ?? [];
    $member = $transaction->member;
    $who = $member
        ? e($member->name).' <span class="muted">&middot; '.$member->code.' &middot; '.e($member->department).'</span>'
        : '';
    $code = fn ($value) => '<code>'.e((string) ($value !== '' && $value !== null ? $value : 'None')).'</code>';
@endphp

@if ($transaction->type === \App\Models\Transaction::DEPOSIT)
    <x-kv :pairs="[
        ['Member', $who],
        ['Month covered', \App\Support\Format::month((string) ($meta['month'] ?? ''))],
        ['Channel', e((string) ($meta['channel'] ?? ''))],
        ['Reference', $code($meta['ref'] ?? '')],
        ['Value date', \App\Support\Format::date($transaction->value_date->toDateString())],
        ['Savings before', \App\Support\Format::ugx((int) ($balances[$transaction->member_id]['balance'] ?? 0))],
    ]" />
@endif

@if ($transaction->type === \App\Models\Transaction::LOAN_BOOKING)
    @php
        $loan = $transaction->loan;
        $schedule = \App\Services\LoanSchedule::build(
            (int) $transaction->amount,
            (float) ($meta['rate'] ?? $loan?->rate ?? 0),
            (int) ($meta['months'] ?? $loan?->months ?? 1),
            (string) ($meta['method'] ?? $loan?->method ?? 'flat'),
            $transaction->value_date->toDateString()
        );
    @endphp
    <x-kv :pairs="[
        ['Borrower', $who],
        ['Period', (int) ($meta['months'] ?? 0).' months at '.\App\Support\Format::rate((float) ($meta['rate'] ?? 0)).' a month, '.e((string) ($meta['method'] ?? ''))],
        ['Monthly installment', \App\Support\Format::ugx((int) ($schedule[0]['total'] ?? 0))],
        ['Total interest', \App\Support\Format::ugx((int) array_sum(array_column($schedule, 'interest')))],
        ['Purpose', e((string) ($meta['purpose'] ?? $loan?->purpose ?? ''))],
        ['Guarantors', $loan && $loan->guarantors->count()
            ? $loan->guarantors->map(fn ($g) => e($g->name))->implode(', ')
            : 'None'],
        ['Appraisal note', e((string) ($loan?->appraisal_note ?? ''))],
    ]" />

    @if (! empty($meta['exception']))
        <div class="warnbox">
            <x-icon name="alert" />
            <span>Booked as a policy exception, so it needs the Patron.</span>
        </div>
    @endif

    @if ($transaction->isPending() && $loan)
        <div>
            <div class="lbl-s" style="margin-bottom:6px">Eligibility now</div>
            <x-checks :checks="\App\Support\Eligibility::checks($loan->member, (int) $transaction->amount, (int) ($meta['months'] ?? 1), [
                'treasury' => true,
                'excludeLoan' => $loan->id,
                'addBack' => (int) $transaction->amount,
            ])" />
        </div>
    @endif
@endif

@if ($transaction->type === \App\Models\Transaction::REPAYMENT)
    @php
        $ledger = $ledgers[$transaction->loan_id] ?? null;
    @endphp
    <x-kv :pairs="[
        ['Borrower', $who],
        ['Loan', '<span class="mono">'.e((string) ($ledger['loan']->ref ?? '')).'</span>'],
        ['Balance now', \App\Support\Format::ugx((int) ($ledger['balance'] ?? 0))],
        ['In arrears', ($ledger['arrears'] ?? 0) > 0
            ? \App\Support\Format::ugx((int) $ledger['arrears']).' &middot; '.(int) $ledger['daysLate'].' days'
            : 'No'],
        ['Channel', e((string) ($meta['channel'] ?? ''))],
        ['Reference', $code($meta['ref'] ?? '')],
        ! empty($meta['via_exit']) ? ['Source', 'Offset from exit settlement'] : null,
    ]" />
@endif

@if ($transaction->type === \App\Models\Transaction::INTEREST_POSTING)
    <x-kv :pairs="[
        ['Month', \App\Support\Format::month((string) ($meta['month'] ?? ''))],
        ['Loan interest collected', \App\Support\Format::ugx((int) ($meta['income'] ?? 0))],
        ['External interest paid', \App\Support\Format::ugx((int) ($meta['external_interest'] ?? 0))],
        ['Shared to savers', (int) ($meta['share'] ?? 0).'% of net'],
        ['Pool', \App\Support\Format::ugx((int) ($meta['pool'] ?? $transaction->amount))
            .(! empty($meta['adjusted']) ? ' (adjusted by Treasurer)' : '')],
        ['Savers credited', $transaction->allocations->count()],
    ]" />

    @include('partials.treasury._allocations', [
        'allocations' => $transaction->allocations->map(fn ($a) => [
            'member_id' => $a->member_id,
            'base' => $a->base,
            'amount' => (int) $a->amount,
        ])->all(),
        'heading' => 'Show the '.$transaction->allocations->count().' member shares',
    ])
@endif

@if ($transaction->type === \App\Models\Transaction::INTEREST_PAYOUT)
    <x-kv :pairs="[
        ['Year', e((string) ($meta['year'] ?? ''))],
        ['Savers paid', $transaction->allocations->count()],
        ['Rule', 'Interest only. Principal stays in PEG.'],
    ]" />

    @include('partials.treasury._allocations', [
        'allocations' => $transaction->allocations->map(fn ($a) => [
            'member_id' => $a->member_id,
            'base' => null,
            'amount' => (int) $a->amount,
        ])->all(),
        'heading' => 'Show the '.$transaction->allocations->count().' payouts',
    ])
@endif

@if ($transaction->type === \App\Models\Transaction::EXIT_SETTLEMENT)
    <x-kv :pairs="[
        ['Member', $who],
        ['Exit date', \App\Support\Format::date((string) ($meta['exit_date'] ?? ''))],
        ['Reason', e((string) ($meta['reason'] ?? ''))],
        ['Principal savings', \App\Support\Format::ugx((int) ($meta['savings'] ?? 0))],
        ['Accumulated interest', \App\Support\Format::ugx((int) ($meta['interest'] ?? 0))],
        ['Loan offset', (int) ($meta['offset'] ?? 0) > 0
            ? \App\Support\Format::ugx((int) $meta['offset']).' ('.e((string) \App\Models\Loan::query()
                ->find((int) ($meta['loan_id'] ?? 0), ['ref'])?->ref).')'
            : 'None'],
        ['Net payable', '<b>'.\App\Support\Format::ugx((int) ($meta['net'] ?? 0)).'</b>'],
    ]" />
@endif

@if ($transaction->type === \App\Models\Transaction::EXTERNAL_LOAN)
    @php
        $facility = $transaction->externalLoan;
        $facilityInfo = $facility?->info() ?? [];
    @endphp
    <x-kv :pairs="[
        ['Lender', e((string) ($facility?->lender ?? $meta['lender'] ?? ''))],
        ['Rate', \App\Support\Format::rate((float) ($facility?->rate ?? 0)).' a month, flat'],
        ['Period', (int) ($facility?->months ?? 0).' months'],
        ['Monthly installment', \App\Support\Format::ugx((int) ($facilityInfo['instalment_principal'] ?? 0)
            + (int) ($facilityInfo['instalment_interest'] ?? 0))],
        ['Total interest', \App\Support\Format::ugx((int) ($facilityInfo['total_interest'] ?? 0))],
        ['Purpose', e((string) ($facility?->purpose ?? $meta['purpose'] ?? ''))],
    ]" />
@endif

@if ($transaction->type === \App\Models\Transaction::EXTERNAL_REPAYMENT)
    @php
        $facility = $transaction->externalLoan;
        $before = (int) ($facility?->info()['outstanding'] ?? 0) + (int) ($meta['principal'] ?? 0);
    @endphp
    <x-kv :pairs="[
        ['Facility', e((string) ($facility?->ref ?? '')).' &middot; '.e((string) ($facility?->lender ?? ''))],
        ['Principal', \App\Support\Format::ugx((int) ($meta['principal'] ?? 0))],
        ['Interest', \App\Support\Format::ugx((int) ($meta['interest'] ?? 0))],
        ['Outstanding before', \App\Support\Format::ugx($before)],
        ['Reference', $code($meta['ref'] ?? '')],
    ]" />
@endif

@if ($transaction->type === \App\Models\Transaction::MEMBERSHIP)
    <x-kv :pairs="[
        ['Name', e((string) ($meta['name'] ?? ''))],
        ['Department', e((string) ($meta['dept'] ?? ''))],
        ['Extension', e((string) ($meta['ext'] ?? '')) ?: 'None'],
        ['Monthly pledge', \App\Support\Format::ugx((int) ($meta['monthly'] ?? 0))],
        ['Joining date', \App\Support\Format::date((string) ($meta['joined'] ?? ''))],
        ['Submitted by', e((string) ($transaction->maker?->name ?? '')).' &middot; '
            .\App\Support\PermissionCatalog::label((string) $transaction->maker_role)],
    ]" />
@endif
