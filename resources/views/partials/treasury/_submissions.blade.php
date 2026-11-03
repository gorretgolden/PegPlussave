{{-- The whole submission log. A check is done from here: the filter holds the pending entries, the
     same table shows what has been posted, and nothing is edited in place. --}}
@php
    $chips = [
        'pending' => 'Pending',
        'posted' => 'Posted',
        'rejected' => 'Rejected',
        'all' => 'All',
    ];
@endphp

<div class="sec">
    <x-chips :options="$chips" :current="$filter" />
    <span class="sub">{{ $entries->count() }} shown{{ in_array($filter, ['posted', 'all'], true) ? ' · latest first' : '' }}</span>
</div>

@include('partials._txn-table', ['entries' => $entries])
