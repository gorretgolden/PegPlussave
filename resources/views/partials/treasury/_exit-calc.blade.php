{{-- The settlement a member taking their money with them. The set-off is the point of showing it
     beside the member box: the loan balance comes out of the pot before the Treasurer types
     anything, and a member who owes more than they saved cannot be paid out of the book. --}}
@php
    $pairs = [
        ['Principal savings', \App\Support\Format::ugx($settlement['savings'])],
        ['Accumulated interest', \App\Support\Format::ugx($settlement['interest'])],
        ['Less loan balance', $settlement['offset'] > 0
            ? '<span class="neg">−'.\App\Support\Format::ugx($settlement['offset']).'</span> ('.$settlement['loan_ref'].')'
            : 'None'],
        ['Net payable', '<b>'.\App\Support\Format::ugx($settlement['net']).'</b>'],
    ];
@endphp

<x-kv :pairs="$pairs" />

@if (\App\Support\ExitSettlement::short($settlement))
    <div class="badbox">
        <x-icon name="alert" />
        <span>The loan balance is more than the savings. Recover
            {{ \App\Support\Format::ugx(-$settlement['net']) }} before settling.</span>
    </div>
@endif
