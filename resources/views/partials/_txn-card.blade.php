{{-- One entry, read the way it has to be checked: what it is, whose money it is, the figures behind
     it, the stamps it already carries, and the two answers available to the officer in front of it.
     The comment box is shared by both buttons through formaction, so a checker writes one sentence
     and points it at either answer. --}}
@php
    $field = 'comment-'.$transaction->id;
    $madeByYou = $transaction->maker_id === auth()->id();
    $approveLabel = (auth()->user()->hasRole('patron') || auth()->user()->hasRole('admin'))
        ? 'Authorise and post'
        : ($transaction->escalated ? 'Approve and send to Patron' : 'Approve and post');
@endphp

<article class="ap-card">
    <header class="ap-h">
        <div>
            <div class="ap-type">
                {{ $transaction->typeLabel() }}
                @if ($transaction->escalated)
                    <x-pill tone="gold">Patron case</x-pill>
                @endif
                <x-pill :tone="\App\Support\Trail::transactionTone($transaction->status)">{{ $transaction->statusLabel() }}</x-pill>
            </div>
            <div class="ap-ref">
                <a class="mono" href="{{ route('entries.show', $transaction) }}">{{ $transaction->ref }}</a>
                &middot; {{ $transaction->party() }}
            </div>
        </div>
        <div class="ap-amt">{{ \App\Support\Format::cash($transaction->amount) }}</div>
    </header>

    @include('partials.entries._details')

    <x-trail :steps="\App\Support\Trail::forTransaction($transaction)" />

    @if ($transaction->notes->count())
        <div class="notes">
            @foreach ($transaction->notes as $note)
                <div class="note">
                    <b>{{ $note->user?->name }} &middot; {{ \App\Support\PermissionCatalog::label($note->role) }}</b>
                    <span>{{ \App\Support\Format::dateTime($note->noted_at?->toDateTimeString()) }}</span>
                    <p>{{ $note->text }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if ($actionable ?? false)
        @if ($madeByYou)
            <div class="selfnote">You made this entry, so another officer must check it.</div>
        @else
            @error($field)<div class="form-err">{{ $message }}</div>@enderror
            <form method="POST" action="{{ route('approvals.approve', $transaction) }}" class="ap-act">
                @csrf
                <label class="sr" for="c-{{ $transaction->id }}">Comment for {{ $transaction->ref }}</label>
                <input id="c-{{ $transaction->id }}" name="{{ $field }}" placeholder="Comment (required to reject)"
                       value="{{ old($field) }}">
                <button class="btn danger" type="submit" formaction="{{ route('approvals.reject', $transaction) }}">Reject</button>
                <button class="btn primary" type="submit">
                    <x-icon name="check" />{{ $approveLabel }}
                </button>
            </form>
        @endif
    @endif
</article>
