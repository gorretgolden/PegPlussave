<?php

namespace App\Support;

use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;

/**
 * The PEG calendar is the meeting rhythm plus whatever the Secretary adds. Three things happen every
 * month without anybody writing them down: contributions are due, the committee meets, and interest
 * is shared out. The rhythm is worked out from the policy each time the calendar is read, so the day
 * the contribution date moves the calendar moves with it; the dated entries a person typed are the
 * only ones stored.
 */
class PegCalendar
{
    public const DEADLINE = 'deadline';

    public const MEETING = 'meeting';

    public const FINANCE = 'finance';

    /**
     * The dated events of a year, oldest first. A recurring entry carries no id because nobody
     * typed it, so no screen offers to delete it.
     *
     * @return array<int, array{id: ?int, date: string, title: string, type: string, notes: ?string, recurring: bool}>
     */
    public static function year(int $year): array
    {
        $rhythm = [];

        foreach (range(1, 12) as $month) {
            $yearMonth = sprintf('%04d-%02d', $year, $month);
            $last = (int) substr(PegDates::monthEnd($yearMonth), 8, 2);

            $rhythm[] = self::row(null, $yearMonth.'-'.str_pad((string) min((int) Policy::get('contribution_day'), $last), 2, '0', STR_PAD_LEFT), 'Monthly contribution deadline', self::DEADLINE, true);
            $rhythm[] = self::row(null, $yearMonth.'-'.str_pad((string) self::firstFriday($year, $month), 2, '0', STR_PAD_LEFT), 'Committee meeting', self::MEETING, true);
            $rhythm[] = self::row(null, $yearMonth.'-'.str_pad((string) $last, 2, '0', STR_PAD_LEFT), 'Monthly interest posting', self::FINANCE, true);
        }

        $typed = CalendarEvent::query()
            ->whereRaw("DATE_FORMAT(date,'%Y') = ?", [$year])
            ->get()
            ->map(fn (CalendarEvent $event) => self::row($event->id, $event->date->toDateString(), $event->title, $event->type, false, $event->notes))
            ->all();

        $events = array_merge($rhythm, $typed);
        usort($events, fn (array $a, array $b) => $a['date'] <=> $b['date']);

        return $events;
    }

    /**
     * @return array<int, array{id: ?int, date: string, title: string, type: string, notes: ?string, recurring: bool}>
     */
    public static function upcoming(int $count, ?string $from = null): array
    {
        $from = $from ?: now()->toDateString();
        $year = (int) substr($from, 0, 4);
        $events = array_merge(self::year($year), self::year($year + 1));

        return array_slice(array_values(array_filter($events, fn (array $e) => $e['date'] >= $from)), 0, $count);
    }

    /**
     * The next event of a kind, the way a chairperson asks it: "when do we next meet?"
     *
     * @return array{id: ?int, date: string, title: string, type: string, notes: ?string, recurring: bool}|null
     */
    public static function next(string $type, ?string $from = null): ?array
    {
        return collect(self::upcoming(80, $from))->firstWhere('type', $type);
    }

    /**
     * Everything dated inside one month, for the calendar screen.
     *
     * @return array<int, array{id: ?int, date: string, title: string, type: string, notes: ?string, recurring: bool}>
     */
    public static function month(string $yearMonth): array
    {
        return array_values(array_filter(
            array_merge(self::year((int) substr($yearMonth, 0, 4)), self::year((int) substr($yearMonth, 0, 4) + 1)),
            fn (array $e) => str_starts_with($e['date'], $yearMonth)
        ));
    }

    /**
     * The committee meets on the first Friday of the month, which is the day the payroll people can
     * get out of the office.
     */
    public static function firstFriday(int $year, int $month): int
    {
        $first = CarbonImmutable::create($year, $month, 1);

        return 1 + (5 - $first->dayOfWeek + 7) % 7;
    }

    private static function row(?int $id, string $date, string $title, string $type, bool $recurring, ?string $notes = null): array
    {
        return ['id' => $id, 'date' => $date, 'title' => $title, 'type' => $type, 'notes' => $notes, 'recurring' => $recurring];
    }
}
