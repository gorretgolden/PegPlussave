{{-- The Treasurer's desk and the book the rest of the treasury team reads. One screen, seven views,
     all of them derived from the same posted entries: nobody keeps a second copy of the totals. --}}
@extends('layouts.peg')

@php
    $badge = fn (int $count) => $count ? ' <span class="tn">'.$count.'</span>' : '';

    $tabs = [
        'overview' => 'Overview',
        'requests' => 'Loan requests'.$badge($counts['requests']),
        'portfolio' => 'Loan portfolio',
        'savings' => 'Savings'.$badge($counts['notices']),
        'submissions' => 'Submissions',
        'external' => 'External loans',
        'exits' => 'Exits'.$badge($counts['exits']),
    ];
@endphp

@section('content')
    <x-conf-banner :can-make="$isTreasurer" />

    <x-tabs :options="$tabs" :current="$tab" label="Treasury views" />

    @include('partials.treasury._'.$tab)
@endsection

@section('modals')
    @isset($modals)
        @if ($isTreasurer)
            @include('partials.treasury._modals')
        @endif
    @endisset

    @if ($tab === 'requests')
        @foreach ($waiting as $row)
            @include('partials._loan-modal', [
                'ledger' => $ledgers[$row['loan']->id],
                'treasuryActions' => true,
            ])
            @if ($isTreasurer)
                @include('partials.treasury._appraise-modal', ['loan' => $row['loan']])
            @endif
        @endforeach
        @foreach ($decided as $loan)
            @include('partials._loan-modal', [
                'ledger' => $ledgers[$loan->id],
                'treasuryActions' => true,
            ])
        @endforeach
    @elseif ($tab === 'portfolio')
        @foreach ($loans as $row)
            @include('partials._loan-modal', [
                'ledger' => $row,
                'treasuryActions' => true,
            ])
        @endforeach
    @elseif ($tab === 'savings')
        @if ($isTreasurer)
            @foreach ($notices as $notice)
                @include('partials.treasury._notice-modal', ['members' => $modals['members']])
            @endforeach
        @endif
        @foreach ($savers as $row)
            @include('partials._member-modal', [
                'member' => $row['member'],
                'row' => $group['balances'][$row['member']->id] ?? [],
            ])
        @endforeach
    @endif
@endsection
