<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\Prospect;
use App\Models\Reminder;
use App\Models\Transaction;
use App\Support\PegDates;
use App\Support\Policy;
use App\Support\Refs;
use App\Support\TreasuryPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Chasing the month, not the money. This desk exists to answer "who has not saved yet", so it names
 * people and dates and never an amount: how much a member put in belongs to the treasury team.
 */
class MobiliseController extends Controller
{
    /** The three stages a prospect is moved through by hand. Registering and declining are decided
     *  elsewhere: one by the Chairperson's approval, the other by the person being asked. */
    public const BOARD = ['Approached', 'Interested', 'Registered'];

    public function index(Request $request)
    {
        $month = now()->format('Y-m');
        $user = $request->user();

        $position = TreasuryPosition::contributions($month);
        $deadline = $this->deadline($month);

        $lastSaved = Transaction::query()
            ->where('type', Transaction::DEPOSIT)
            ->whereNotNull('member_id')
            ->whereNot('status', Transaction::REJECTED)
            ->get(['member_id', 'meta', 'value_date', 'status'])
            ->reduce(function (array $carry, Transaction $entry) {
                $ym = ($entry->meta['month'] ?? null) ?: $entry->value_date->format('Y-m');
                if (! $entry->isPosted() || strcmp((string) ($carry[$entry->member_id] ?? ''), $ym) >= 0) {
                    return $carry;
                }
                $carry[$entry->member_id] = $ym;

                return $carry;
            }, []);

        $reminders = Reminder::query()->where('month', $month)
            ->orderBy('created_at')
            ->get()
            ->groupBy('member_id');

        $registered = Prospect::query()->whereIn('stage', ['Registered', 'Member'])
            ->pluck('name')
            ->all();

        $waiting = Transaction::query()
            ->where('type', Transaction::MEMBERSHIP)
            ->where('status', 'like', 'pending%')
            ->pluck('meta')
            ->map(fn (array $meta) => $meta['name'] ?? '')
            ->filter(fn (string $name) => in_array($name, $registered, true))
            ->all();

        return view('pages.mobilise', [
            'page' => 'mobilise',
            'month' => $month,
            'position' => $position,
            'deadline' => $deadline,
            'daysLeft' => PegDates::daysBetween($deadline, now()->toDateString()),
            'lastSaved' => $lastSaved,
            'reminders' => $reminders,
            'stages' => array_map(fn (string $stage) => [
                'stage' => $stage,
                'prospects' => Prospect::query()->where('stage', $stage)
                    ->orderBy('name')->get(),
                'awaiting' => $stage === 'Registered' ? $waiting : [],
            ], self::BOARD),
            'canMobilise' => $user->can('add-prospect'),
            'canRemind' => $user->can('view-mobilisation'),
            'canRegister' => $user->can('register-member'),
            'departments' => Member::DEPARTMENTS,
        ]);
    }

    public function storeProspect(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'dept' => ['required', Rule::in(Member::DEPARTMENTS)],
            'note' => ['nullable', 'string', 'max:200'],
        ], [], ['name' => 'name', 'dept' => 'department', 'note' => 'note']);

        if ($validator->fails()) {
            session()->flash('open_modal', 'dlg-prospect');

            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $prospect = Prospect::create([
            'ref' => Refs::prospect(),
            'name' => $data['name'],
            'department' => $data['dept'],
            'stage' => 'Approached',
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::record($request->user(), 'Added '.$prospect->name.' to the membership drive', $prospect->ref);

        return redirect()->route('pages.mobilise')->with('status', $prospect->name.' added to the drive');
    }

    public function moveProspect(Request $request, Prospect $prospect)
    {
        $stage = $request->input('stage');
        abort_unless(in_array($stage, ['Interested', 'Approached'], true), 422, 'That stage cannot be set by hand.');
        abort_unless(in_array($prospect->stage, ['Approached', 'Interested'], true), 422, 'This prospect is already registered or has gone.');

        $prospect->update(['stage' => $stage]);

        ActivityLog::record(
            $request->user(),
            'Moved '.$prospect->name.' to '.$stage.' in the membership drive',
            $prospect->ref
        );

        return redirect()->route('pages.mobilise')->with('status', $prospect->name.' marked '.$stage);
    }

    /**
     * A reminder is written down so the committee can see the follow-up happened. Nobody is sent
     * anything from here: the mobiliser speaks to the member, and the log is the record of it.
     */
    public function remind(Request $request, Member $member)
    {
        abort_unless($member->isActive(), 422, 'This member has left.');

        $month = now()->format('Y-m');

        Reminder::create([
            'member_id' => $member->id,
            'month' => $month,
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::record($request->user(), 'Logged a contribution reminder for '.$member->name, $member->code);

        return redirect()->route('pages.mobilise')
            ->with('status', 'Reminder logged for '.$member->name);
    }

    /**
     * The day the month is due, pulled back to the last day of short months so a deadline of the
     * 31st still means something in February.
     */
    private function deadline(string $month): string
    {
        $day = min((int) Policy::get('contribution_day'), (int) substr(PegDates::monthEnd($month), 8, 2));

        return $month.'-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT);
    }
}
