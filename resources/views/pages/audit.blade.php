{{-- Every action in the system, newest first. The list is the same one the books are kept by, so a
     figure can be traced to a person and a time without opening another screen. --}}
@extends('layouts.peg')

@section('content')
    @if ($isOwner)
        <div class="conf-banner">
            <x-icon name="lock" />
            <span><b>Confidential.</b> Financial entries are hidden from the System Owner account.
                The Chairperson, Patron and Accountant see the full trail.
                @if ($hidden)
                    {{ $hidden }} {{ \Illuminate\Support\Str::plural('money line', $hidden) }} recorded since the books opened are kept from this view.
                @endif</span>
        </div>
    @endif

    <div class="sec">
        <div>
            <div class="sub">{{ $entries->count() }} latest events &middot; every make, check and authorise is recorded with the person, role and time</div>
        </div>
        <div class="sec-r">
            <label class="sr" for="au-s">Search the trail</label>
            <input id="au-s" class="btn sm" placeholder="Search" data-search="tbody.au tr">
        </div>
    </div>

    <div class="tbl">
        <table>
            <thead>
            <tr>
                <th>When</th>
                <th>Who</th>
                <th>Role</th>
                <th>Action</th>
                <th>Ref</th>
            </tr>
            </thead>
            <tbody class="au">
            @foreach ($entries as $entry)
                <tr>
                    <td class="mono" style="white-space: nowrap">{{ \App\Support\Format::dateTime((string) $entry->created_at) }}</td>
                    <td>{{ $entry->name ?: 'A removed account' }}</td>
                    <td>{{ \App\Support\PermissionCatalog::label($entry->role) }}</td>
                    <td>{{ $entry->action }}</td>
                    <td class="mono">{{ $entry->subject }}</td>
                </tr>
            @endforeach
            @if ($entries->isEmpty())
                <x-empty-row :cols="5" message="Nothing has been recorded yet." />
            @endif
            </tbody>
        </table>
    </div>
@endsection
