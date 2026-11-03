{{-- The desk as a whole: what the box holds, what the Treasurer can do about it, and everything
     that has been typed and not yet believed. --}}
<div class="kpis">
    <x-kpi label="Cash at bank (book)" :value="\App\Support\Format::cash($group['cash'])"
           sub="All posted receipts less payments" />
    <x-kpi label="Members' savings" :value="\App\Support\Format::cash($group['total_savings'])"
           sub="Principal held for members" />
    <x-kpi label="Loans outstanding" :value="\App\Support\Format::cash($group['outstanding_principal'])"
           :sub="count($group['running']).' running · PAR 30 '.number_format($group['par30_percent'], 1).'%'" />
    <x-kpi label="Available to lend" :value="\App\Support\Format::cash($group['available'])"
           :sub="'After reserve and '.\App\Support\Format::ugx($group['committed']).' in approval'" tone="ok" />
</div>

@if ($isTreasurer)
    @php
        $entries = [
            ['dlg-deposit', 'wallet', 'Record deposit', 'Monthly saving, minimum '.\App\Support\Policy::short((int) \App\Support\Policy::get('min_monthly'))],
            ['dlg-loanbook-t', 'loan', 'Book a loan', 'For a member, direct or on paper'],
            ['dlg-repay', 'check', 'Record repayment', 'Against a running loan'],
            ['dlg-interest', 'percent', 'Post monthly interest', 'Share loan interest to savers'],
            ['dlg-ext', 'bank', 'External borrowing', 'PEG borrows from a lender'],
            ['dlg-extrepay', 'out', 'Repay external loan', 'Installment to a lender'],
            ['dlg-payout', 'inbox', 'Annual interest payout', \App\Support\Format::monthName('2000-'.str_pad((string) \App\Support\Policy::get('payout_month'), 2, '0', STR_PAD_LEFT).'-01').', interest only'],
            ['dlg-exit', 'flag', 'Exit settlement', 'Member leaving PlusSave'],
        ];
    @endphp
    <section class="stack">
        <x-sec title="Make an entry" sub="Each entry is held for the Chairperson. Patron cases continue to the Patron." />
        <div class="actgrid">
            @foreach ($entries as $entry)
                <button class="act" data-open="{{ $entry[0] }}">
                    <span class="ai"><x-icon :name="$entry[1]" /></span>
                    <span><b>{{ $entry[2] }}</b><span>{{ $entry[3] }}</span></span>
                </button>
            @endforeach
        </div>
    </section>
@endif

<section class="stack">
    <x-sec title="In the approval chain">
        <x-slot:actions>
            <span class="sub">{{ $chain->count() }} entries · {{ \App\Support\Format::ugx($chain->sum('amount')) }}</span>
        </x-slot:actions>
    </x-sec>
    @include('partials._txn-table', ['entries' => $chain])
</section>

<section class="stack">
    <x-sec title="Recently posted">
        <x-slot:actions>
            <a class="btn sm" href="{{ route('pages.treasury', ['tab' => 'submissions']) }}">All submissions</a>
        </x-slot:actions>
    </x-sec>
    @include('partials._txn-table', ['entries' => $recent])
</section>
