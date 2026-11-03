{{-- What the month actually earned, before anybody decides how much of it to share. Two warnings
     belong here rather than in the validation: a month that has not closed yet, and a month that
     has already been posted or is still waiting to be checked. --}}
@php
    $share = (int) \App\Support\Policy::get('distribution_share');
    $pairs = [
        ['Loan interest collected', \App\Support\Format::ugx($computed['income'])],
        ['External interest paid', \App\Support\Format::ugx($computed['external_interest'])],
        ['Net interest', \App\Support\Format::ugx($computed['net'])],
        ['Suggested pool ('.$share.'%)', \App\Support\Format::ugx($computed['pool'])],
        ['Savers credited', count($allocations)],
        ['Total credited', '<b>'.\App\Support\Format::ugx(array_sum(array_column($allocations, 'amount'))).'</b>'],
    ];
@endphp

@if ($duplicate)
    <div class="badbox">
        <x-icon name="alert" />
        <span>Interest for {{ \App\Support\Format::month($month) }} is already
            {{ $duplicate->isPosted() ? 'posted' : 'waiting for checks' }} ({{ $duplicate->ref }}).</span>
    </div>
@endif

@if (\App\Support\PegDates::monthEnd($month) >= now()->toDateString())
    <div class="warnbox">
        <x-icon name="clock" />
        <span>{{ \App\Support\Format::month($month) }} has not ended. Repayments still to come this month will not be included.</span>
    </div>
@endif

<x-kv :pairs="$pairs" />
