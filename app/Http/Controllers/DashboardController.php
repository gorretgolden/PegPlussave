<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Notice;
use App\Models\PaymentNotice;
use App\Models\Prospect;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InterestDistribution;
use App\Support\Format;
use App\Support\PegCalendar;
use App\Support\PegDates;
use App\Support\Policy;
use App\Support\TreasuryPosition;
use Illuminate\Support\Facades\DB;

/**
 * The first screen after signing in. It answers the two questions a person has when they open the
 * system: where I stand myself, and what is waiting on my desk today.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $position = $user->can('view-treasury') ? TreasuryPosition::group() : null;

        return view('pages.dashboard', [
            'page' => 'dashboard',
            'activeSavers' => Member::query()->active()->count(),
            'greeting' => self::greeting(),
            'firstName' => $user->roleKey() === 'admin'
                ? explode(' ', $user->name)[0]
                : Format::given($user->name),
            'roleLine' => self::ROLE_LINES[$user->roleKey()] ?? '',
            'mine' => $user->isMember() ? $this->myPosition($user) : null,
            'group' => $position,
            'notices' => Notice::query()->ordered()->take(3)->get(),
            'events' => PegCalendar::upcoming(5),
            'desk' => $this->desk($user, $position),
        ]);
    }

    /**
     * Where a member stands: what they have saved, what the group owes them in interest, the loan
     * they are repaying, and whether this month's saving has been recorded yet.
     */
    private function myPosition(User $user): array
    {
        $member = Member::query()->findOrFail($user->member_id);
        $balances = TreasuryPosition::memberBalances();
        $row = $balances[$member->id] ?? ['balance' => 0, 'interest' => 0, 'months' => [], 'deposits_by_month' => []];
        $month = now()->format('Y-m');

        $running = null;

        foreach (TreasuryPosition::loanLedgers() as $ledger) {
            if ($ledger['loan']->member_id === $member->id && $ledger['loan']->disbursed_on && $ledger['state'] !== 'Closed') {
                $running = $ledger;
                break;
            }
        }

        $pending = Transaction::query()
            ->where('member_id', $member->id)
            ->where('type', Transaction::DEPOSIT)
            ->where('status', 'like', 'pending%')
            ->get(['meta'])
            ->contains(fn (Transaction $t) => ($t->meta['month'] ?? null) === $month);

        return [
            'member' => $member,
            'balance' => $row['balance'],
            'months' => count($row['months']),
            'interest' => $row['interest'],
            'running' => $running,
            'month' => $month,
            'saved_this_month' => $row['deposits_by_month'][$month] ?? 0,
            'pending_this_month' => $pending,
            'due_line' => Format::ordinal(min((int) Policy::get('contribution_day'), (int) substr(PegDates::monthEnd($month), 8, 2)))
                .' '.Format::monthName($month),
            'exiting' => (bool) $member->exit_flagged,
            'has_running_loan' => $running !== null,
        ];
    }

    /**
     * What is waiting on this office. A row with no number is a standing fact of the desk, not
     * something the person can clear, so it is shown with a clock rather than a zero.
     *
     * @return array<int, array{n: ?int, label: string, btn: ?string, href: ?string, modal: ?string}>
     */
    private function desk(User $user, ?array $position): array
    {
        $role = $user->roleKey();

        if ($role === 'member') {
            return [];
        }

        $rows = [];
        $add = function (array $row) use (&$rows) {
            $rows[] = $row + ['btn' => null, 'href' => null, 'modal' => null];
        };

        $pendingChair = Transaction::query()->where('status', Transaction::PENDING_CHAIR);
        $pendingPatron = Transaction::query()->where('status', Transaction::PENDING_PATRON);

        if ($role === 'treasurer') {
            $add(['n' => Loan::query()->where('status', Loan::REQUESTED)->count(),
                'label' => 'Loan requests waiting for your appraisal', 'btn' => 'Appraise',
                'href' => route('pages.treasury', ['tab' => 'requests'])]);
            $add(['n' => PaymentNotice::query()->where('status', PaymentNotice::OPEN)->count(),
                'label' => 'Payment notices from members to record', 'btn' => 'Record',
                'href' => route('pages.treasury', ['tab' => 'savings'])]);
            $add(['n' => Transaction::query()->where('status', 'like', 'pending%')->where('maker_id', $user->id)->count(),
                'label' => 'Your entries waiting to be checked', 'btn' => 'View',
                'href' => route('pages.treasury', ['tab' => 'submissions'])]);
            $add(['n' => Transaction::query()->where('status', Transaction::REJECTED)->where('maker_id', $user->id)->count(),
                'label' => 'Entries returned to you (rejected)', 'btn' => 'View',
                'href' => route('pages.treasury', ['tab' => 'submissions'])]);
            $add(['n' => Member::query()->active()->where('exit_flagged', true)->count(),
                'label' => 'Members leaving PlusSave to settle', 'btn' => 'Settle',
                'href' => route('pages.treasury', ['tab' => 'exits'])]);

            $month = InterestDistribution::nextOpenMonth();
            $due = PegDates::monthEnd($month);
            $add(['n' => null,
                'label' => 'Interest for '.Format::month($month).': '.
                    ($due < now()->toDateString() ? 'due for posting now' : 'post after '.Format::date($due)),
                'btn' => 'Post interest', 'modal' => 'dlg-interest']);
        }

        if ($role === 'chair') {
            $add(['n' => (clone $pendingChair)->count(), 'label' => 'Entries waiting for your check',
                'btn' => 'Review', 'href' => route('pages.approvals', ['tab' => 'mine'])]);
            $add(['n' => (clone $pendingChair)->where('type', Transaction::MEMBERSHIP)->count(),
                'label' => 'New member registrations to approve', 'btn' => 'Review',
                'href' => route('pages.approvals', ['tab' => 'mine'])]);
            $add(['n' => (clone $pendingPatron)->count(), 'label' => 'Approved by you, waiting for the Patron',
                'btn' => 'View', 'href' => route('pages.approvals', ['tab' => 'other'])]);
            $meeting = PegCalendar::next('meeting');
            $add(['n' => null, 'label' => 'Next committee meeting: '.($meeting ? Format::date($meeting['date']) : 'not set'),
                'btn' => 'Calendar', 'href' => route('pages.calendar')]);
        }

        if ($role === 'patron') {
            $threshold = (int) Policy::get('patron_threshold');
            $add(['n' => (clone $pendingPatron)->count(),
                'label' => 'Exceptional cases waiting for your authorisation', 'btn' => 'Review',
                'href' => route('pages.approvals', ['tab' => 'mine'])]);
            $add(['n' => Loan::query()->disbursed()->where('amount', '>', $threshold)->count(),
                'label' => 'Loans above '.Format::ugx($threshold).' authorised', 'btn' => 'Report',
                'href' => route('pages.reports', ['tab' => 'disbursed'])]);
            $add(['n' => null,
                'label' => 'External borrowing outstanding: '.Format::ugx($position['external_outstanding'] ?? 0),
                'btn' => 'View', 'href' => route('pages.treasury', ['tab' => 'external'])]);
        }

        if ($role === 'accountant') {
            $month = now()->format('Y-m');
            $add(['n' => Transaction::query()->posted()
                ->whereDate('value_date', '>=', $month.'-01')
                ->whereDate('value_date', '<=', PegDates::monthEnd($month))->count(),
                'label' => 'Entries posted in '.Format::month($month), 'btn' => 'Cash book',
                'href' => route('pages.ledger')]);
            $add(['n' => Transaction::query()->where('status', 'like', 'pending%')
                ->whereHas('notes', fn ($q) => $q->where('role', 'accountant'))->count(),
                'label' => 'Pending entries carrying your review notes', 'btn' => 'View',
                'href' => route('pages.treasury', ['tab' => 'submissions'])]);
            $add(['n' => null, 'label' => 'Cash book balance: '.Format::ugx($position['cash'] ?? 0),
                'btn' => 'Reconcile', 'href' => route('pages.ledger')]);
            $add(['n' => null, 'label' => 'Statement of financial position', 'btn' => 'Open',
                'href' => route('pages.reports', ['tab' => 'funds'])]);
        }

        if ($role === 'secretary') {
            $add(['n' => Member::query()->active()->count(), 'label' => 'Active members on the register',
                'btn' => 'Directory', 'href' => route('pages.directory')]);
            $add(['n' => Member::query()->active()->where('exit_flagged', true)->count(),
                'label' => 'Exit notices passed to the Treasurer', 'btn' => 'View',
                'href' => route('pages.directory')]);
            $meeting = PegCalendar::next('meeting');
            $add(['n' => null, 'label' => 'Next committee meeting: '.($meeting ? Format::date($meeting['date']) : 'not set'),
                'btn' => 'Record minutes', 'modal' => 'dlg-minutes']);
            $add(['n' => CalendarEvent::query()->whereDate('date', '>=', now()->toDateString())->count(),
                'label' => 'Planned PEG activities ahead', 'btn' => 'Add activity', 'modal' => 'dlg-event']);
        }

        if ($role === 'spokesman') {
            $add(['n' => Notice::query()->count(), 'label' => 'Notices on the board',
                'btn' => 'Post notice', 'modal' => 'dlg-notice']);
            $toAnnounce = collect(PegCalendar::upcoming(40))->reject(fn (array $e) => $e['recurring'])->take(3);
            $add(['n' => $toAnnounce->count(),
                'label' => 'To announce: '.($toAnnounce->pluck('title')->implode(', ') ?: 'nothing planned'),
                'btn' => 'Calendar', 'href' => route('pages.calendar')]);
        }

        if ($role === 'mobiliser') {
            $add(['n' => TreasuryPosition::contributions()['not_yet']->count(),
                'label' => 'Members yet to save for '.Format::month(now()->format('Y-m')),
                'btn' => 'Follow up', 'href' => route('pages.mobilise')]);
            $add(['n' => Prospect::query()->where('stage', '!=', 'Member')->count(),
                'label' => 'Staff in the membership pipeline', 'btn' => 'Pipeline',
                'href' => route('pages.mobilise')]);
        }

        if ($role === 'admin') {
            $add(['n' => User::query()->count(), 'label' => 'User accounts',
                'btn' => 'Manage', 'href' => route('pages.admin', ['tab' => 'users'])]);
            $add(['n' => null, 'label' => 'Policy settings: rates, limits and thresholds',
                'btn' => 'Settings', 'href' => route('pages.admin', ['tab' => 'settings'])]);
            $add(['n' => null, 'label' => 'PlusSave logo on every menu',
                'btn' => 'Branding', 'href' => route('pages.admin', ['tab' => 'branding'])]);
        }

        return $rows;
    }

    private static function greeting(): string
    {
        $hour = (int) now()->hour;

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    private const ROLE_LINES = [
        'member' => 'Here is where your PEG savings and loans stand today.',
        'chair' => 'You check every Treasurer entry before it posts.',
        'treasurer' => 'You make the entries. The Chairperson checks them, and large or exceptional ones go on to the Patron.',
        'patron' => 'You authorise the exceptional cases: large loans, policy exceptions, external borrowing, exits and the annual payout.',
        'accountant' => 'You review the books, reconcile the cash book and raise queries on entries.',
        'secretary' => 'You keep the members register, the minutes and the PEG calendar.',
        'spokesman' => 'You publish notices and keep members informed.',
        'mobiliser' => 'You drive monthly contributions and bring new staff into PEG.',
        'admin' => 'You manage user accounts, policy settings and branding. Financial records stay hidden from this account.',
    ];
}
