{{-- The next things on the calendar. The monthly rhythm is worked out when the page is read, so a
     change to the contribution day shows up here before anybody writes a notice about it. --}}
<section class="panel">
    <x-sec title="Coming up">
        <x-slot:actions>
            <a class="btn sm" href="{{ route('pages.calendar') }}">Calendar</a>
        </x-slot:actions>
    </x-sec>
    @foreach ($events as $event)
        @include('partials._event', ['event' => $event, 'canDelete' => false])
    @endforeach
</section>
