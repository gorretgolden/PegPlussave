@extends('layouts.peg')

@section('content')
    @php
        $u = auth()->user();
        $payoutMonth = \Carbon\CarbonImmutable::parse('2000-'.str_pad((string) \App\Support\Policy::get('payout_month'), 2, '0', STR_PAD_LEFT).'-01')->format('F');
    @endphp

    <section class="hero">
        <div>
            <div class="eyebrow">{{ \App\Support\Format::date(now()->toDateString()) }} · {{ $u->roleLabel() }}</div>
            <h2 class="hero-h">{{ $greeting }}, {{ $firstName }}</h2>
            <p class="hero-p">{{ $roleLine }}</p>
        </div>
        @if ($u->member_id)
            <div class="hero-act">
                <button class="btn" data-open="dlg-paynotice"><x-icon name="upload" />Notify a payment</button>
                <button class="btn primary" data-open="dlg-loanbook"><x-icon name="plus" />Book a loan</button>
            </div>
        @endif
    </section>

    @if ($mine)
        <div class="group-label">My PEG</div>
        <div class="kpis">
            <x-kpi label="Principal savings" :value="\App\Support\Format::cash($mine['balance'])"
                   :sub="$mine['months'].' months · pledge '.\App\Support\Format::ugx($mine['member']->monthly_pledge)" />
            <x-kpi label="Interest accumulated" :value="\App\Support\Format::cash($mine['interest'])"
                   :sub="'Paid out in '.$payoutMonth" tone="gold" />
            @if ($mine['running'])
                @php $next = $mine['running']['next']; @endphp
                <x-kpi label="Loan balance" :value="\App\Support\Format::cash($mine['running']['balance'])"
                       :sub="$mine['running']['state'] === 'In arrears'
                            ? 'In arrears '.$mine['running']['daysLate'].' days'
                            : 'Next '.\App\Support\Format::ugx($next['total'] - $next['paidInterest'] - $next['paidPrincipal']).' on '.\App\Support\Format::date($next['due'])"
                       :tone="$mine['running']['state'] === 'In arrears' ? 'bad' : ''" />
            @else
                <x-kpi label="Loan balance" value="None" sub="No running loan" />
            @endif
            <x-kpi :label="\App\Support\Format::monthTag($mine['month'].'-01').' saving'"
                   :value="$mine['saved_this_month'] ? \App\Support\Format::cash($mine['saved_this_month']) : ($mine['pending_this_month'] ? 'Being checked' : 'Not yet')"
                   :sub="$mine['saved_this_month'] ? 'Recorded' : ($mine['pending_this_month'] ? 'Treasurer has recorded it' : 'Due by '.$mine['due_line'])"
                   :tone="$mine['saved_this_month'] ? 'ok' : ($mine['pending_this_month'] ? '' : 'warn')" />
        </div>

        @if ($mine['exiting'])
            <div class="warnbox">
                <x-icon name="alert" />
                <span>Your exit notice is recorded. The Treasurer will settle your savings and interest{{ $mine['has_running_loan'] ? ' less your loan balance' : '' }}.</span>
            </div>
        @endif
    @endif

    @if ($group)
        <div class="group-label">Group position</div>
        <div class="kpis">
            <x-kpi label="Members' savings" :value="\App\Support\Format::cash($group['total_savings'])"
                   :sub="$activeSavers.' active savers'" />
            <x-kpi label="Loans outstanding" :value="\App\Support\Format::cash($group['outstanding_principal'])"
                   :sub="count($group['running']).' running loans'" />
            <x-kpi label="Available to lend" :value="\App\Support\Format::cash($group['available'])"
                   :sub="'After '.\App\Support\Policy::get('reserve_percent').'% reserve'" tone="ok" />
            <x-kpi label="PAR 30" :value="number_format($group['par30_percent'], 1).'%'"
                   sub="Principal over 30 days late" :tone="$group['par30_percent'] > 5 ? 'bad' : ''" />
        </div>
    @endif

    @if ($desk !== [])
        <div class="cols-21">
            @include('partials._desk')
            @include('partials._coming-up')
        </div>
        @include('partials._notice-board', ['manage' => false])
    @else
        <div class="cols-21">
            @include('partials._notice-board', ['manage' => false])
            @include('partials._coming-up')
        </div>
    @endif
@endsection
