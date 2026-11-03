<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Support\PegCalendar;
use App\Support\Refs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The calendar is the meeting rhythm plus whatever the Secretary adds on top of it. Nothing here is
 * stored except the dated entries a person typed; the deadlines, meetings and postings come from the
 * policy each time the page is read, so moving the contribution day moves the whole year.
 */
class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $year = $this->year($request);
        $types = $this->types($request);
        $events = array_values(array_filter(
            PegCalendar::year($year),
            fn (array $event) => $types[$event['type']] ?? false
        ));

        $byDate = [];
        foreach ($events as $event) {
            $byDate[$event['date']][] = $event;
        }

        return view('pages.calendar', [
            'page' => 'calendar',
            'year' => $year,
            'types' => $types,
            'day' => $this->day($request, $year, $byDate),
            'byDate' => $byDate,
            'plan' => array_values(array_filter($events, fn (array $e) => ! $e['recurring'])),
            'canAdd' => $request->user()->can('add-event'),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:120'],
            'date' => ['required', 'date'],
            'type' => ['required', 'in:'.implode(',', array_keys(CalendarEvent::TYPES))],
            'notes' => ['nullable', 'string', 'max:400'],
        ], [], ['title' => 'title', 'date' => 'date', 'type' => 'type']);

        if ($validator->fails()) {
            session()->flash('open_modal', 'dlg-event');

            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $event = DB::transaction(fn () => CalendarEvent::create([
            'ref' => Refs::event(),
            'date' => $data['date'],
            'title' => $data['title'],
            'type' => $data['type'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]));

        ActivityLog::record($request->user(), 'Added '.$event->title.' to the calendar', $event->ref);

        return redirect()
            ->route('pages.calendar', [
                'year' => substr((string) $event->date->toDateString(), 0, 4),
                'day' => $event->date->toDateString(),
            ])
            ->with('status', 'Added to the calendar');
    }

    public function destroy(Request $request, CalendarEvent $event)
    {
        $title = $event->title;
        $event->delete();
        ActivityLog::record($request->user(), 'Removed '.$title.' from the calendar', $event->ref);

        return back()->with('status', 'Removed from the calendar');
    }

    /**
     * Which kinds of entry are drawn. Everything is on until a reader switches a type off, and the
     * choice travels in the address so a calendar can be handed over showing exactly what it showed.
     *
     * @return array<string, bool>
     */
    private function types(Request $request): array
    {
        $keys = array_keys(CalendarEvent::TYPES);
        $types = array_fill_keys($keys, true);

        if (! $request->has('types')) {
            return $types;
        }

        $chosen = array_intersect($keys, explode(',', (string) $request->query('types')));

        return array_map(fn (string $type) => in_array($type, $chosen, true), $types);
    }

    private function year(Request $request): int
    {
        $year = (int) $request->query('year', now()->format('Y'));

        return $year >= 2000 && $year <= 2100 ? $year : (int) now()->format('Y');
    }

    /**
     * The day whose list sits beside the year. A reader may pick any day of the shown year; the
     * default is today when the year is this one, because that is the day a person asks about.
     */
    private function day(Request $request, int $year, array $byDate): string
    {
        $day = (string) $request->query('day');
        $prefix = $year.'-';

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 && str_starts_with($day, $prefix)) {
            return $day;
        }

        $today = now()->toDateString();

        return str_starts_with($today, $prefix) ? $today : $prefix.'01-01';
    }
}
