{{-- The loan the repayment is being typed against, as it stands today. Interest is settled before
     principal, so the amount the box suggests is what the entry will actually split. --}}
@php
    use App\Support\Format;

    $next = $ledger['next'];

    $pairs = [
        ['Borrower', e($ledger['loan']->member?->name)],
        ['Balance', Format::ugx($ledger['balance'])],
        ['In arrears', $ledger['arrears']
            ? '<span class="neg">'.Format::ugx($ledger['arrears']).' · '.$ledger['daysLate'].' days</span>'
            : 'No'],
        ['Next installment', $next
            ? Format::ugx($next['total'] - $next['paidInterest'] - $next['paidPrincipal']).' on '.Format::date($next['due'])
            : 'None'],
        $waiting ? ['Already waiting for checks', Format::ugx($waiting)] : null,
    ];
@endphp

<x-kv :pairs="$pairs" />
