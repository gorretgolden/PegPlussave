{{-- The members register. Names are filtered on the server, so the chips and the department box are
     addresses a reader can send to somebody else; the search box only narrows what is already here,
     because a register read in a meeting is a list of people, not a query. --}}
@extends('layouts.peg')

@php
    $chips = [
        'active' => 'With us ('.$counts['active'].')',
        'left' => 'Left ('.$counts['left'].')',
        'all' => 'All ('.$counts['all'].')',
    ];
    $keep = fn (array $over = []) => array_merge(['status' => $status, 'dept' => $department], $over);
@endphp

@section('content')
    <div class="sec">
        <x-chips :options="$chips" :current="$status" param="status" />
        <div class="sec-r">
            <form method="GET" action="{{ route('pages.directory') }}" class="inline" data-submit>
                <input type="hidden" name="status" value="{{ $status }}">
                <label class="sr" for="dd">Department</label>
                <select id="dd" name="dept" class="btn sm">
                    <option value="all" @selected($department === 'all')>All departments</option>
                    @foreach ($departments as $option)
                        <option value="{{ $option }}" @selected($option === $department)>{{ $option }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn sm" type="submit">Go</button></noscript>
            </form>
            <label class="sr" for="dir-search">Search members</label>
            <input id="dir-search" class="btn sm" placeholder="Search name or number" data-search="tbody.dir tr">
            @if ($canRegister)
                <button class="btn sm primary" type="button" data-open="dlg-addmember"><x-icon name="plus" />Register member</button>
            @endif
        </div>
    </div>

    @if ($waiting->isNotEmpty())
        <div class="warnbox">
            <x-icon name="clock" />
            <span>Waiting for the Chairperson to approve:
                @foreach ($waiting as $entry)
                    <b>{{ $entry->meta['name'] ?? '' }}</b>{{ ! $loop->last ? ',' : '' }}
                    ({{ $entry->meta['dept'] ?? '' }})
                @endforeach
            </span>
        </div>
    @endif

    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>No.</th>
                <th>Name</th>
                <th>Department</th>
                <th>PEG role</th>
                <th>Ext.</th>
                <th>Joined</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody class="dir">
            @foreach ($members as $member)
                <tr class="click" data-open="dlg-member-{{ $member->id }}">
                    <td class="mono">{{ $member->code }}</td>
                    <td><b>{{ $member->name }}</b></td>
                    <td>{{ $member->department }}</td>
                    <td>
                        @php $office = $member->user?->roleKey() ?? 'member'; @endphp
                        @if ($office !== 'member')
                            <x-pill tone="info">{{ $member->user?->roleLabel() }}</x-pill>
                        @else
                            Member
                        @endif
                    </td>
                    <td class="mono">{{ $member->extension }}</td>
                    <td>{{ \App\Support\Format::date($member->joined_on?->toDateString()) }}</td>
                    <td>
                        @if ($member->status === 'left')
                            <x-pill tone="muted">Left {{ \App\Support\Format::date($member->exit_on?->toDateString()) }}</x-pill>
                        @elseif ($member->exit_flagged)
                            <x-pill tone="warn">Leaving</x-pill>
                        @else
                            <x-pill tone="ok">Active</x-pill>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if ($members->isEmpty())
                <x-empty-row :cols="7" message="No members in this view." />
            @endif
            </tbody>
        </table>
    </div>
@endsection

@section('modals')
    @foreach ($members as $member)
        @include('partials._member-modal', [
            'member' => $member,
            'row' => $rows[$member->id] ?? [],
            'loanLedgers' => $ledgers,
        ])

        @if (auth()->user()->can('edit-member') && $member->isActive())
            @include('partials._member-edit', ['member' => $member, 'minimum' => $minimum, 'departments' => $departments])
        @endif
    @endforeach

    @if ($canRegister)
        @include('partials._member-add', [
            'minimum' => $minimum,
            'departments' => $departments,
            'prospect' => $prospect,
        ])
    @endif
@endsection
