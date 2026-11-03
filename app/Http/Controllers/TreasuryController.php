<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ExternalLoan;
use App\Models\Loan;
use App\Models\Member;
use App\Models\PaymentNotice;
use App\Models\Transaction;
use App\Services\EntryWorkflow;
use App\Services\InterestDistribution;
use App\Support\Eligibility;
use App\Support\ExitSettlement;
use App\Support\Format;
use App\Support\LoanFacts;
use App\Support\PegDates;
use App\Support\Policy;
use App\Support\Refs;
use App\Support\Trail;
use App\Support\TreasuryPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Treasurer's working desk and the book everyone else reads. Seven views of the same derived
 * position: what is waiting to be appraised, what is lent out, whose saving has not landed, what
 * the group owes its lenders, and what a member who is leaving takes with them. Only the Treasurer
 * can make an entry here; the Chairperson, Accountant and Patron see the same screen without the
 * buttons, which is how a check is done without asking for a copy of the book.
 */
class TreasuryController extends Controller
{
    public const TABS = ['overview', 'requests', 'portfolio', 'savings', 'submissions', 'external', 'exits'];

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';
        $user = $request->user();

        $group = TreasuryPosition::group();
        $isTreasurer = $user->can('make-treasury-entry');

        $data = [
            'page' => 'treasury',
            'tab' => $tab,
            'isTreasurer' => $isTreasurer,
            'group' => $group,
            'filter' => $this->filter($request, $tab),
            'counts' => [
                'requests' => Loan::query()->where('status', Loan::REQUESTED)->count(),
                'notices' => PaymentNotice::query()->where('status', PaymentNotice::OPEN)->count(),
                'exits' => Member::query()->active()->where('exit_flagged', true)->count(),
            ],
            'pendingMonth' => InterestDistribution::nextOpenMonth(),
        ];

        $data = array_merge($data, match ($tab) {
            'requests' => $this->requests(),
            'portfolio' => $this->portfolio($data['filter'], $group),
            'savings' => $this->savings($group),
            'submissions' => $this->submissions($data['filter']),
            'external' => $this->externalLoans(),
            'exits' => $this->exits(),
            default => $this->overview($group),
        });

        if ($isTreasurer && in_array($tab, ['overview', 'requests', 'portfolio', 'savings', 'external', 'exits'], true)) {
            $data['facts'] = LoanFacts::for();
            $data['loanPolicy'] = LoanFacts::policy($tab === 'requests' || $tab === 'portfolio'
                ? TreasuryPosition::available()
                : $group['available']);
            $data['modals'] = $this->modals($group);
        }

        return view('pages.treasury', $data);
    }

    /**
     * The lists every entry box is built from. They are read here, once, so a box opened from any
     * tab offers the same members, the same running loans and the same lenders.
     *
     * @return array<string, mixed>
     */
    private function modals(array $group): array
    {
        $ledgers = $group['ledgers'];

        return [
            'members' => Member::query()->active()->orderBy('code')->get(),
            'running' => collect($ledgers)
                ->filter(fn (array $ledger) => $ledger['loan']->disbursed_on && $ledger['balance'] > 0)
                ->sortBy(fn (array $ledger) => $ledger['loan']->ref)
                ->values(),
            'lenders' => ExternalLoan::query()
                ->where('status', ExternalLoan::ACTIVE)
                ->get()
                ->filter(fn (ExternalLoan $loan) => $loan->info()['outstanding'] > 0),
            'payout' => $this->payoutPreview($group),
        ];
    }

    /**
     * The annual payout is a list of who is owed what, so the box can say the total before the
     * Treasurer sends it. It is the same figure the posting writes: interest credited and not yet
     * drawn, for the members still in the group.
     *
     * @return array{year: string, allocations: array<int, array{member_id: int, amount: int}>, month: int, inWindow: bool}
     */
    private function payoutPreview(array $group): array
    {
        $month = (int) Policy::get('payout_month');
        $active = Member::query()->active()->pluck('id')->all();

        $allocations = collect($group['balances'])
            ->only($active)
            ->map(fn (array $row, int $memberId) => ['member_id' => $memberId, 'amount' => max(0, (int) $row['interest'])])
            ->filter(fn (array $row) => $row['amount'] > 0)
            ->values()
            ->all();

        return [
            'year' => now()->format('Y'),
            'allocations' => $allocations,
            'month' => $month,
            'inWindow' => (int) now()->format('n') === $month,
        ];
    }

    private function filter(Request $request, string $tab): string
    {
        $options = match ($tab) {
            'portfolio' => ['running', 'arrears', 'approval', 'closed', 'all'],
            'submissions' => ['pending', 'posted', 'rejected', 'all'],
            default => [''],
        };

        return in_array($request->query('filter'), $options, true) ? $request->query('filter') : $options[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(array $group): array
    {
        return [
            'chain' => $this->transactions(fn ($query) => $query->where('status', 'like', 'pending%')),
            'recent' => $this->transactions(fn ($query) => $query->posted()->latest('made_at')->take(8)),
        ];
    }

    /**
     * A member's request arrives here before it is a booking. The seven checks are shown as the
     * Treasurer will read them, including the funds one, because the box has to hold the money.
     *
     * @return array<string, mixed>
     */
    private function requests(): array
    {
        $waiting = Loan::query()
            ->with(['member', 'guarantors'])
            ->where('status', Loan::REQUESTED)
            ->orderBy('requested_at')
            ->get()
            ->map(function (Loan $loan) {
                $checks = Eligibility::checks($loan->member, (int) $loan->amount, (int) $loan->months, [
                    'treasury' => true,
                    'excludeLoan' => $loan->id,
                ]);

                return ['loan' => $loan, 'checks' => $checks, 'met' => Eligibility::met($checks)];
            });

        $decided = Loan::query()
            ->with('member')
            ->whereColumn('requested_by', 'member_id')
            ->where('status', '!=', Loan::REQUESTED)
            ->orderByDesc('requested_at')
            ->take(10)
            ->get();

        return [
            'waiting' => $waiting,
            'decided' => $decided,
            'ledgers' => TreasuryPosition::loanLedgers(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function portfolio(string $filter, array $group): array
    {
        $rows = collect($group['ledgers'])
            ->filter(fn (array $ledger) => $ledger['loan']->disbursed_on || $ledger['loan']->status === Loan::IN_APPROVAL)
            ->map(fn (array $ledger) => array_merge($ledger, [
                'state' => Trail::loanState($ledger['loan'], $ledger),
            ]));

        $picked = match ($filter) {
            'arrears' => $rows->filter(fn (array $r) => $r['state'] === 'In arrears'),
            'approval' => $rows->filter(fn (array $r) => $r['state'] === 'In approval' || $r['state'] === 'With Patron'),
            'closed' => $rows->filter(fn (array $r) => $r['state'] === 'Closed'),
            'all' => $rows,
            default => $rows->filter(fn (array $r) => $r['loan']->disbursed_on && $r['balance'] > 0),
        };

        $list = $picked->sortBy([
            [fn (array $r) => $r['daysLate'], 'desc'],
            [fn (array $r) => $r['loan']->disbursed_on?->toDateString() ?? '', 'desc'],
        ])->values();

        return ['loans' => $list];
    }

    /**
     * @return array<string, mixed>
     */
    private function savings(array $group): array
    {
        $month = now()->format('Y-m');

        $waiting = Transaction::query()
            ->where('type', Transaction::DEPOSIT)
            ->where('status', 'like', 'pending%')
            ->get(['member_id', 'meta'])
            ->filter(fn (Transaction $t) => ($t->meta['month'] ?? null) === $month)
            ->pluck('member_id')
            ->unique();

        $members = Member::query()->active()->orderBy('name')->get()
            ->map(function (Member $member) use ($group, $month, $waiting) {
                $row = $group['balances'][$member->id] ?? [];

                return [
                    'member' => $member,
                    'balance' => (int) ($row['balance'] ?? 0),
                    'interest' => (int) ($row['interest'] ?? 0),
                    'months' => count($row['months'] ?? []),
                    'last' => $row['last_deposit'] ?? null,
                    'saved' => isset($row['deposits_by_month'][$month]),
                    'checking' => $waiting->contains($member->id),
                ];
            });

        return [
            'notices' => PaymentNotice::query()->with('member')->where('status', PaymentNotice::OPEN)
                ->orderBy('paid_on')->get(),
            'savers' => $members,
            'monthTag' => Format::monthTag($month.'-01'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function submissions(string $filter): array
    {
        return [
            'entries' => $this->transactions(fn ($query) => $query
                ->when($filter === 'pending', fn ($q) => $q->where('status', 'like', 'pending%'))
                ->when($filter === 'posted', fn ($q) => $q->posted())
                ->when($filter === 'rejected', fn ($q) => $q->where('status', Transaction::REJECTED))
                ->limit(in_array($filter, ['posted', 'all'], true) ? 120 : 500)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function externalLoans(): array
    {
        return [
            'facilities' => ExternalLoan::query()
                ->with(['transactions' => fn ($query) => $query->orderBy('id')])
                ->latest('id')
                ->get()
                ->map(fn (ExternalLoan $loan) => [
                    'loan' => $loan,
                    'info' => $loan->info(),
                    'booking' => $loan->transactions->firstWhere('type', Transaction::EXTERNAL_LOAN),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exits(): array
    {
        return [
            'leaving' => Member::query()->active()->where('exit_flagged', true)->orderBy('name')->get()
                ->map(function (Member $member) {
                    $settlement = ExitSettlement::calculate($member);
                    $pending = Transaction::query()
                        ->where('member_id', $member->id)
                        ->where('type', Transaction::EXIT_SETTLEMENT)
                        ->where('status', 'like', 'pending%')
                        ->exists();

                    return ['member' => $member, 'settlement' => $settlement, 'pending' => $pending];
                }),
            'settlements' => $this->transactions(fn ($query) => $query
                ->where('type', Transaction::EXIT_SETTLEMENT)
                ->orderByDesc('value_date')),
        ];
    }

    /**
     * The rows a table is built from, with everything a row has to say already loaded: a name, the
     * officer who made it, and the dots showing how far it has travelled.
     */
    private function transactions(callable $scope): \Illuminate\Support\Collection
    {
        $query = Transaction::query()->with(['member', 'externalLoan', 'maker', 'checks', 'notes', 'allocations', 'loan']);

        return $scope($query)->orderByDesc('made_at')->get();
    }

    /**
     * What a month would pay out if it were posted today, as the markup the panel is made of. The
     * arithmetic is the posting's own, so the figure under the pool box is the figure that would be
     * written into the book.
     */
    public function figures(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'pool' => ['nullable', 'integer', 'min:0'],
        ]);

        $month = $data['month'] ?? InterestDistribution::nextOpenMonth();
        $computed = InterestDistribution::compute($month);
        $pool = isset($data['pool']) ? (int) $data['pool'] : (int) $computed['pool'];
        $allocations = InterestDistribution::allocate($pool, $computed['bases']);

        $duplicate = Transaction::query()
            ->where('type', Transaction::INTEREST_POSTING)
            ->where('meta->month', $month)
            ->whereNot('status', Transaction::REJECTED)
            ->first();

        return response()->json([
            'pool' => (int) $computed['pool'],
            'html' => view('partials.treasury._interest-calc', [
                'month' => $month,
                'computed' => $computed,
                'pool' => $pool,
                'allocations' => $allocations,
                'duplicate' => $duplicate,
            ])->render(),
            'allocHtml' => view('partials.treasury._allocations', [
                'allocations' => $allocations,
                'heading' => "Show each saver's share (pro rata to savings at month end)",
            ])->render(),
        ]);
    }

    /**
     * What one loan owes today, as the panel under the amount box: the balance, what is behind,
     * when the next installment falls, and the figure the Treasurer is most likely typing.
     */
    public function repayFigures(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['loan' => ['required', 'integer', 'exists:loans,id']]);

        $ledger = TreasuryPosition::loanLedgers()[$data['loan']] ?? null;

        if (! $ledger) {
            return response()->json(['html' => ''], 404);
        }

        $waiting = \App\Services\EntryWorkflow::pendingRepayments((int) $data['loan']);
        $next = $ledger['next'];
        $due = $ledger['arrears'] > 0
            ? $ledger['arrears']
            : ($next ? $next['total'] - $next['paidInterest'] - $next['paidPrincipal'] : $ledger['balance']);

        return response()->json([
            'suggest' => max(0, min($due, $ledger['balance'] - $waiting)),
            'html' => view('partials.treasury._repay-calc', [
                'ledger' => $ledger,
                'waiting' => $waiting,
            ])->render(),
        ]);
    }

    /**
     * What the group owes a member who leaves, worked out at the exit date being typed, in the same
     * words the settlement will be booked with.
     */
    public function exitFigures(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'member' => ['required', 'integer', 'exists:members,id'],
            'as_of' => ['nullable', 'date'],
        ]);

        return response()->json([
            'html' => view('partials.treasury._exit-calc', [
                'settlement' => ExitSettlement::calculate((int) $data['member'], $data['as_of'] ?? now()->toDateString()),
            ])->render(),
        ]);
    }

    /* ------------------------------------------------------------------ entries

       Each box below stops at the same place: an entry waiting for the Chairperson. Nothing here
       moves a balance, because the money only moves when the check is done by somebody who did not
       type this. */

    public function deposit(Request $request)
    {
        $min = (int) Policy::get('min_monthly');

        $data = $request->validate([
            'dep_member' => ['required', 'integer', 'exists:members,id'],
            'dep_amount' => ['required', 'integer', 'min:'.$min],
            'dep_month' => ['required', 'date_format:Y-m'],
            'dep_date' => ['required', 'date'],
            'dep_channel' => ['required', 'string', Rule::in(Transaction::CHANNELS)],
            'dep_ref' => ['nullable', 'string', 'max:60'],
            'dep_notice' => ['nullable', 'integer', 'exists:payment_notices,id'],
        ], [
            'dep_amount.min' => 'Enter the amount received, at least '.Format::ugx($min).'.',
            'dep_month.required' => 'Say which month this saving covers.',
        ]);

        if ($request->filled('dep_notice')) {
            $notice = PaymentNotice::query()->findOrFail((int) $data['dep_notice']);

            if ($notice->status !== PaymentNotice::OPEN) {
                return $this->fail('dlg-deposit', ['dep_notice' => 'That notice has already been recorded.']);
            }
        }

        $entry = EntryWorkflow::make([
            'type' => Transaction::DEPOSIT,
            'member_id' => (int) $data['dep_member'],
            'amount' => (int) $data['dep_amount'],
            'value_date' => $data['dep_date'],
            'meta' => [
                'month' => $data['dep_month'],
                'channel' => $data['dep_channel'],
                'ref' => trim((string) $data['dep_ref']),
            ],
        ], $request->user());

        if (isset($notice)) {
            $notice->update(['status' => PaymentNotice::RECORDED, 'transaction_id' => $entry->id]);
        }

        ActivityLog::record($request->user(), "Recorded a deposit of {$entry->amount}", $entry->ref);

        return $this->back('savings', "{$entry->ref} sent to the Chairperson for checking");
    }

    /**
     * A booking made by the office rather than asked for by the member. The same seven checks are
     * run; what differs is that the Treasurer may go outside them, but only in writing, and only by
     * handing the decision to the Patron.
     */
    public function bookLoan(Request $request)
    {
        $data = $request->validate([
            'lb_member' => ['required', 'integer', 'exists:members,id'],
            'lb_amount' => ['required', 'integer', 'min:'.MemberLoanController::MINIMUM],
            'lb_months' => ['required', 'integer', 'min:1', 'max:'.Policy::get('max_months')],
            'lb_purpose' => ['required', 'string', Rule::in(Transaction::PURPOSES)],
            'lb_detail' => ['nullable', 'string', 'max:180'],
            'lb_note' => ['nullable', 'string', 'max:400'],
        ], [
            'lb_amount.min' => 'Enter a loan amount of at least '.Format::ugx(MemberLoanController::MINIMUM).'.',
        ]);

        $member = Member::query()->findOrFail((int) $data['lb_member']);
        $amount = (int) $data['lb_amount'];
        $months = (int) $data['lb_months'];
        $note = trim((string) ($data['lb_note'] ?? ''));
        $outside = Eligibility::hard(Eligibility::checks($member, $amount, $months, ['treasury' => true]));

        if ($outside !== [] && $note === '') {
            return $this->fail('dlg-loanbook-t', [
                'lb_note' => 'This loan is outside policy. Add a note explaining the exception; it will go to the Patron.',
            ]);
        }

        $escalate = $amount > (int) Policy::get('patron_threshold') || $outside !== [];

        $loan = DB::transaction(fn () => $this->recordTreasuryLoan($member, $data, $amount, $months, $note, $request->user()));

        $entry = EntryWorkflow::make([
            'type' => Transaction::LOAN_BOOKING,
            'member_id' => $member->id,
            'loan_id' => $loan->id,
            'amount' => $amount,
            'escalated' => $escalate,
            'meta' => [
                'months' => $months,
                'rate' => (float) $loan->rate,
                'method' => $loan->method,
                'purpose' => $loan->purpose,
                'exception' => $outside !== [],
            ],
        ], $request->user());

        ActivityLog::record($request->user(), "Booked loan {$loan->ref} for {$member->name}", $loan->ref);

        return $this->back('portfolio', "{$entry->ref} sent to the Chairperson".($escalate ? ', then the Patron' : ''));
    }

    public function appraise(Request $request, Loan $loan)
    {
        $note = trim((string) $request->input('ap_note', ''));
        $decision = $request->input('ap_decision') === 'decline' ? 'decline' : 'submit';

        if ($decision === 'decline') {
            if ($note === '') {
                return $this->fail("dlg-appraise-{$loan->id}", ['ap_note' => 'Give the reason for declining so the member knows.']);
            }

            $loan->update([
                'status' => Loan::DECLINED,
                'appraised_by' => $request->user()->id,
                'appraised_at' => now(),
                'appraisal_decision' => 'declined',
                'appraisal_note' => $note,
            ]);

            ActivityLog::record($request->user(), "Declined loan request {$loan->ref}", $loan->ref);

            return $this->back('requests', "{$loan->ref} declined");
        }

        $data = $request->validate([
            'ap_amount' => ['required', 'integer', 'min:'.MemberLoanController::MINIMUM],
            'ap_months' => ['required', 'integer', 'min:1', 'max:'.Policy::get('max_months')],
        ], [
            'ap_amount.min' => 'Enter a loan amount of at least '.Format::ugx(MemberLoanController::MINIMUM).'.',
        ]);

        $amount = (int) $data['ap_amount'];
        $months = (int) $data['ap_months'];
        $outside = Eligibility::hard(Eligibility::checks($loan->member, $amount, $months, [
            'treasury' => true,
            'excludeLoan' => $loan->id,
        ]));

        if ($outside !== [] && $note === '') {
            return $this->fail("dlg-appraise-{$loan->id}", [
                'ap_note' => 'Outside policy. Add a note explaining the exception; the Patron will review it.',
            ]);
        }

        $escalate = $amount > (int) Policy::get('patron_threshold') || $outside !== [];

        $loan->fill([
            'amount' => $amount,
            'months' => $months,
            'status' => Loan::IN_APPROVAL,
            'appraised_by' => $request->user()->id,
            'appraised_at' => now(),
            'appraisal_decision' => 'recommended',
            'appraisal_note' => $note !== '' ? $note : 'Within policy. Recommended.',
        ])->save();

        $entry = EntryWorkflow::make([
            'type' => Transaction::LOAN_BOOKING,
            'member_id' => $loan->member_id,
            'loan_id' => $loan->id,
            'amount' => $amount,
            'escalated' => $escalate,
            'meta' => [
                'months' => $months,
                'rate' => (float) $loan->rate,
                'method' => $loan->method,
                'purpose' => $loan->purpose,
                'exception' => $outside !== [],
            ],
        ], $request->user());

        ActivityLog::record($request->user(), "Appraised loan request {$loan->ref}", $loan->ref);

        return $this->back('requests', "{$entry->ref} sent to the Chairperson".($escalate ? ', then the Patron' : ''));
    }

    public function repay(Request $request)
    {
        $data = $request->validate([
            'rp_loan' => ['required', 'integer', 'exists:loans,id'],
            'rp_amount' => ['required', 'integer', 'min:1'],
            'rp_date' => ['required', 'date'],
            'rp_channel' => ['required', 'string', Rule::in(Transaction::CHANNELS)],
            'rp_ref' => ['nullable', 'string', 'max:60'],
        ], [
            'rp_amount.min' => 'Enter the amount received.',
        ]);

        $ledger = TreasuryPosition::loanLedgers()[(int) $data['rp_loan']] ?? null;

        if (! $ledger || ! $ledger['loan']->disbursed_on) {
            return $this->fail('dlg-repay', ['rp_loan' => 'Choose a loan that has been paid out.']);
        }

        $room = (int) $ledger['balance'] - EntryWorkflow::pendingRepayments((int) $data['rp_loan']);

        if ((int) $data['rp_amount'] > $room) {
            return $this->fail('dlg-repay', [
                'rp_amount' => 'The most that can be applied is '.Format::ugx(max(0, $room))
                    .' (balance less repayments already waiting).',
            ]);
        }

        $entry = EntryWorkflow::make([
            'type' => Transaction::REPAYMENT,
            'member_id' => $ledger['loan']->member_id,
            'loan_id' => $ledger['loan']->id,
            'amount' => (int) $data['rp_amount'],
            'value_date' => $data['rp_date'],
            'meta' => [
                'channel' => $data['rp_channel'],
                'ref' => trim((string) $data['rp_ref']),
            ],
        ], $request->user());

        ActivityLog::record($request->user(), "Recorded a repayment on {$ledger['loan']->ref}", $entry->ref);

        return $this->back('portfolio', "{$entry->ref} sent to the Chairperson for checking");
    }

    /**
     * The month's interest, shared. The pool is the Treasurer's figure, not the arithmetic's, so a
     * posting that has been adjusted is marked as one: the Chairperson is approving a decision, and
     * the reason it differs from the suggestion has to be visible in the entry.
     */
    public function interest(Request $request)
    {
        $data = $request->validate([
            'in_month' => ['required', 'date_format:Y-m'],
            'in_pool' => ['required', 'integer', 'min:0'],
        ], [
            'in_month.date_format' => 'Choose the month being shared.',
            'in_pool.min' => 'There is no interest to share for this month.',
        ]);

        $month = $data['in_month'];

        $already = Transaction::query()
            ->where('type', Transaction::INTEREST_POSTING)
            ->where('meta->month', $month)
            ->whereNot('status', Transaction::REJECTED)
            ->exists();

        if ($already) {
            return $this->fail('dlg-interest', [
                'in_month' => 'Interest for '.Format::month($month).' is already posted or waiting for checks.',
            ]);
        }

        $pool = (int) $data['in_pool'];

        if ($pool <= 0) {
            return $this->fail('dlg-interest', ['in_pool' => 'There is no interest to share for this month.']);
        }

        $computed = InterestDistribution::compute($month);

        if (InterestDistribution::allocate($pool, $computed['bases']) === []) {
            return $this->fail('dlg-interest', ['in_pool' => 'No member had savings at the end of this month.']);
        }

        $end = PegDates::monthEnd($month);

        $entry = EntryWorkflow::make([
            'type' => Transaction::INTEREST_POSTING,
            'amount' => $pool,
            'value_date' => $end <= now()->toDateString() ? $end : now()->toDateString(),
            'meta' => [
                'month' => $month,
                'pool' => $pool,
                'income' => $computed['income'],
                'external_interest' => $computed['external_interest'],
                'share' => $computed['share'],
                'adjusted' => $pool !== (int) $computed['pool'],
            ],
        ], $request->user());

        ActivityLog::record($request->user(), 'Posted interest for '.Format::month($month), $entry->ref);

        return $this->back('overview', "{$entry->ref} sent to the Chairperson for checking");
    }

    public function payout(Request $request)
    {
        $year = (string) $request->input('pa_year', now()->format('Y'));

        $already = Transaction::query()
            ->where('type', Transaction::INTEREST_PAYOUT)
            ->where('meta->year', $year)
            ->whereNot('status', Transaction::REJECTED)
            ->exists();

        if ($already) {
            return $this->fail('dlg-payout', ['pa_year' => "The {$year} payout is already posted or waiting for approval."]);
        }

        $allocations = $this->payoutPreview(TreasuryPosition::group())['allocations'];

        if ($allocations === []) {
            return $this->fail('dlg-payout', ['pa_year' => 'No member has interest to withdraw.']);
        }

        $entry = EntryWorkflow::make([
            'type' => Transaction::INTEREST_PAYOUT,
            'amount' => array_sum(array_column($allocations, 'amount')),
            'escalated' => true,
            'meta' => ['year' => $year, 'alloc' => $allocations],
        ], $request->user());

        ActivityLog::record($request->user(), "Prepared the {$year} interest payout", $entry->ref);

        return $this->back('overview', "{$entry->ref} sent to the Chairperson, then the Patron");
    }

    public function external(Request $request)
    {
        $data = $request->validate([
            'ex_lender' => ['required', 'string', 'max:120'],
            'ex_amount' => ['required', 'integer', 'min:1'],
            'ex_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'ex_months' => ['required', 'integer', 'min:1', 'max:36'],
            'ex_date' => ['nullable', 'date'],
            'ex_purpose' => ['nullable', 'string', 'max:180'],
        ], [
            'ex_lender.required' => 'Say who the money is coming from.',
            'ex_amount.min' => 'Enter the amount being borrowed.',
        ]);

        $facility = ExternalLoan::create([
            'ref' => Refs::externalLoan(),
            'lender' => trim($data['ex_lender']),
            'amount' => (int) $data['ex_amount'],
            'rate' => (float) $data['ex_rate'],
            'months' => (int) $data['ex_months'],
            'purpose' => trim((string) ($data['ex_purpose'] ?? '')) ?: 'General liquidity',
            'status' => ExternalLoan::IN_APPROVAL,
        ]);

        $entry = EntryWorkflow::make([
            'type' => Transaction::EXTERNAL_LOAN,
            'external_loan_id' => $facility->id,
            'amount' => $facility->amount,
            'value_date' => $data['ex_date'] ?? now()->toDateString(),
            'escalated' => true,
            'meta' => [
                'lender' => $facility->lender,
                'rate' => (float) $facility->rate,
                'months' => $facility->months,
                'purpose' => $facility->purpose,
            ],
        ], $request->user());

        ActivityLog::record($request->user(), "Recorded borrowing from {$facility->lender}", $entry->ref);

        return $this->back('external', "{$entry->ref} sent to the Chairperson, then the Patron");
    }

    public function externalRepay(Request $request)
    {
        $data = $request->validate([
            'xr_loan' => ['required', 'integer', 'exists:external_loans,id'],
            'xr_principal' => ['nullable', 'integer', 'min:0'],
            'xr_interest' => ['nullable', 'integer', 'min:0'],
            'xr_date' => ['required', 'date'],
            'xr_ref' => ['nullable', 'string', 'max:60'],
        ]);

        $facility = ExternalLoan::query()->findOrFail((int) $data['xr_loan']);
        $principal = (int) ($data['xr_principal'] ?? 0);
        $interest = (int) ($data['xr_interest'] ?? 0);
        $outstanding = $facility->info()['outstanding'];

        if ($principal + $interest <= 0) {
            return $this->fail('dlg-extrepay', ['xr_principal' => 'Enter the principal and interest paid.']);
        }

        if ($principal > $outstanding) {
            return $this->fail('dlg-extrepay', [
                'xr_principal' => 'Principal cannot exceed the outstanding '.Format::ugx($outstanding).'.',
            ]);
        }

        $entry = EntryWorkflow::make([
            'type' => Transaction::EXTERNAL_REPAYMENT,
            'external_loan_id' => $facility->id,
            'amount' => $principal + $interest,
            'value_date' => $data['xr_date'],
            'meta' => [
                'principal' => $principal,
                'interest' => $interest,
                'ref' => trim((string) ($data['xr_ref'] ?? '')),
            ],
        ], $request->user());

        if ($outstanding - $principal <= 0) {
            $facility->update(['status' => ExternalLoan::CLOSED]);
        }

        ActivityLog::record($request->user(), "Repaid {$facility->lender}", $entry->ref);

        return $this->back('external', "{$entry->ref} sent to the Chairperson for checking");
    }

    /**
     * A member leaving takes what is theirs, and the loan they still owe is settled out of it. The
     * set-off is booked as a repayment of its own so the money trail of the loan stays whole.
     */
    public function exit(Request $request)
    {
        $data = $request->validate([
            'exi_member' => ['required', 'integer', 'exists:members,id'],
            'exi_date' => ['nullable', 'date'],
            'exi_reason' => ['required', 'string', 'max:80'],
        ], [
            'exi_reason.required' => 'Say why the member is leaving.',
        ]);

        $member = Member::query()->findOrFail((int) $data['exi_member']);
        $settlement = ExitSettlement::calculate($member, $data['exi_date'] ?? now()->toDateString());

        if (ExitSettlement::short($settlement)) {
            return $this->fail('dlg-exit', [
                'exi_member' => 'The loan balance is more than the savings. Recover '.Format::ugx(-$settlement['net'])
                    .' before settling.',
            ]);
        }

        $waiting = Transaction::query()
            ->where('member_id', $member->id)
            ->where('type', Transaction::EXIT_SETTLEMENT)
            ->where('status', 'like', 'pending%')
            ->exists();

        if ($waiting) {
            return $this->fail('dlg-exit', ['exi_member' => 'A settlement for this member is already waiting for approval.']);
        }

        $entry = EntryWorkflow::make([
            'type' => Transaction::EXIT_SETTLEMENT,
            'member_id' => $member->id,
            'amount' => $settlement['savings'] + $settlement['interest'],
            'value_date' => $data['exi_date'] ?? now()->toDateString(),
            'escalated' => true,
            'meta' => [
                'savings' => $settlement['savings'],
                'interest' => $settlement['interest'],
                'offset' => $settlement['offset'],
                'loan_id' => $settlement['loan_id'],
                'net' => $settlement['net'],
                'exit_date' => $data['exi_date'] ?? now()->toDateString(),
                'reason' => $data['exi_reason'],
            ],
        ], $request->user());

        ActivityLog::record($request->user(), "Prepared an exit settlement for {$member->name}", $entry->ref);

        return $this->back('exits', "{$entry->ref} sent to the Chairperson, then the Patron");
    }

    /**
     * A box that came back with a reason has to be found open again, with the words beside the field
     * they belong to rather than in a banner the officer has to read across the screen for.
     */
    private function fail(string $modal, array $messages)
    {
        session()->flash('open_modal', $modal);

        throw ValidationException::withMessages($messages);
    }

    private function back(string $tab, string $status)
    {
        return redirect()->route('pages.treasury', ['tab' => $tab])->with('status', $status);
    }

    private function recordTreasuryLoan(Member $member, array $data, int $amount, int $months, string $note, $user): Loan
    {
        $detail = trim((string) ($data['lb_detail'] ?? ''));

        return Loan::create([
            'ref' => Refs::loan(),
            'member_id' => $member->id,
            'amount' => $amount,
            'months' => $months,
            'rate' => Policy::get('loan_rate'),
            'method' => Policy::get('loan_method'),
            'purpose' => $detail === '' ? $data['lb_purpose'] : $data['lb_purpose'].': '.$detail,
            'status' => Loan::IN_APPROVAL,
            'requested_by' => $user->id,
            'requested_at' => now(),
            'appraised_by' => $user->id,
            'appraised_at' => now(),
            'appraisal_decision' => 'recommended',
            'appraisal_note' => $note !== '' ? $note : 'Booked by the Treasurer. Within policy.',
        ]);
    }
}
