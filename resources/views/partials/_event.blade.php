<div class="ev t-{{ $event['type'] }}">
    <div class="ev-date">
        <b>{{ (int) substr($event['date'], 8, 2) }}</b>
        <span>{{ \App\Support\Format::monthTag($event['date']) }}</span>
    </div>
    <div>
        <div class="ev-type"><i class="dot"></i>{{ \App\Models\CalendarEvent::TYPES[$event['type']] ?? $event['type'] }}</div>
        <div class="ev-t">{{ $event['title'] }}</div>
        @if ($event['notes'])
            <div class="muted small">{{ $event['notes'] }}</div>
        @endif
        @if ($canDelete && $event['id'])
            <form method="POST" action="{{ route('calendar.destroy', $event['id']) }}" class="inline">
                @csrf
                @method('DELETE')
                <button class="link-btn" type="submit">Remove</button>
            </form>
        @endif
    </div>
</div>
