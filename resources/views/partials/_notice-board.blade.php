{{-- The board every member reads. Pinning and removing are the Spokesman's, not a decoration on
     somebody else's notice, so the links only appear for the account that may post. --}}
<section class="panel">
    <x-sec :title="$title ?? 'Notice board'">
        @if (! isset($noAll))
            <x-slot:actions>
                <a class="btn sm" href="{{ route('pages.notices') }}">All notices</a>
            </x-slot:actions>
        @endif
    </x-sec>

    @forelse ($notices as $notice)
        <article class="notice">
            <h4>
                @if ($notice->pinned)<x-icon name="pin" />@endif
                {{ $notice->title }}
            </h4>
            <p>{{ $notice->body }}</p>
            <div class="meta">
                <span>{{ $notice->author?->name }} · {{ \App\Support\PermissionCatalog::label($notice->author_role) }} · {{ \App\Support\Format::date($notice->posted_on?->toDateString()) }}</span>
                @if (! empty($manage))
                    <form class="inline" method="POST" action="{{ route('notices.pin', $notice) }}">
                        @csrf
                        @method('PATCH')
                        <button class="link-btn" type="submit">{{ $notice->pinned ? 'Unpin' : 'Pin' }}</button>
                    </form>
                    <form class="inline" method="POST" action="{{ route('notices.destroy', $notice) }}">
                        @csrf
                        @method('DELETE')
                        <button class="link-btn" type="submit">Remove</button>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="empty">No notices yet.</div>
    @endforelse
</section>
