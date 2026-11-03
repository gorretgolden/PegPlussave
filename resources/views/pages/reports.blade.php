{{-- A report is a way of reading the book, not a second copy of it: every figure on these seven tabs
     comes from the same posted entries the treasury desk works from, and the file a reader downloads
     is built from the rows just shown. --}}
@extends('layouts.peg')

@section('content')
    <x-conf-banner :can-make="$isTreasurer" />

    <x-tabs :options="$tabs" :current="$tab" label="Reports" />

    @include('partials.reports._'.$tab)
@endsection

@section('modals')
    @foreach ($dialogs as $ledger)
        @include('partials._loan-modal', ['ledger' => $ledger, 'treasuryActions' => $isTreasurer])
    @endforeach

    @foreach ($members as $member)
        @include('partials._member-modal', [
            'member' => $member,
            'row' => $group['balances'][$member->id] ?? [],
            'loanLedgers' => $group['ledgers'],
        ])
    @endforeach
@endsection
