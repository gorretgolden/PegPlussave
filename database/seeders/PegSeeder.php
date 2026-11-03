<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\ExternalLoan;
use App\Models\InterestAllocation;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Minute;
use App\Models\Notice;
use App\Models\PaymentNotice;
use App\Models\Prospect;
use App\Models\Reminder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LoanLedger;
use App\Services\LoanSchedule;
use App\Support\PegDates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The reviewed prototype's sample group, moved into the books.
 *
 * Every figure the committee approved on screen came from one generated run, so this seeder replays
 * that run in the same order and with the same random draws rather than inventing numbers that look
 * similar. The pipeline states are what make the demo worth showing: a deposit waiting on the
 * Chairperson, a loan escalated to the Patron, a repayment with the Accountant's query on it, two
 * settled exits and a member flagged as leaving. Those are the screens a reviewer asks about first.
 */
class PegSeeder extends Seeder
{
    private const PATRON_THRESHOLD = 5000000;

    private const DIST_SHARE = 90;

    private DemoRng $rng;

    /** @var array<string, array<string, mixed>> keyed by member code */
    private array $members = [];

    /** @var array<int, array<string, mixed>> */
    private array $txns = [];

    /** @var array<string, array<string, mixed>> keyed by loan ref */
    private array $loans = [];

    private int $seqLn = 0;

    /** @var array<int, string> */
    private array $months = [];

    private const EXIT_PLAN = [
        'PEG-016' => ['2026-06-30', 'Resigned from PlusSave'],
        'PEG-017' => ['2026-08-31', 'Contract ended'],
    ];

    /** The chair checks, the patron authorises, the treasurer makes. */
    private const TREASURER = 'PEG-002';

    private const CHAIR = 'PEG-001';

    private const PATRON = 'PEG-006';

    public function run(): void
    {
        if (Member::query()->exists()) {
            $this->command?->warn('PEG already has members — sample data left alone.');

            return;
        }

        DB::transaction(function () {
            $this->build();
            $this->persist();
        });

        $this->command?->info('PEG sample group: '.count($this->members).' members, '.$this->seqLn.' loans, '.count($this->txns).' entries.');
    }

    /* ------------------------------------------------------------------ build */

    private function build(): void
    {
        $this->rng = new DemoRng(20261103);

        $this->seedMembers();

        for ($m = 1; $m <= 10; $m++) {
            $this->months[] = '2026-'.str_pad((string) $m, 2, '0', STR_PAD_LEFT);
        }

        $this->seedDeposits();
        $this->seedNovember();
        $this->seedLoans();
        $this->seedPipeline();
        $this->seedExternal();
        $this->seedPostingsAndExits();
        $this->seedRegistration();

        $this->members['PEG-008']['exiting'] = true;
        $this->members['PEG-008']['exitNote'] = 'Moving to a new employer. Last day 30 Nov 2026.';
    }

    private function seedMembers(): void
    {
        $rows = [
            ['Edina', 'Operations', 'chair', 200000, '2026-01-09', 'edina', '104'],
            ['Claire', 'Finance', 'treasurer', 150000, '2026-01-09', 'claire', '121'],
            ['Atuhaire Doreen', 'HR & Admin', 'secretary', 100000, '2026-01-09', 'doreen', '131'],
            ['Nkayuuve', 'Marketing', 'spokesman', 250000, '2026-01-09', 'nkayuuve', '142'],
            ['Nakato Shamim', 'Customer Service', 'mobiliser', 80000, '2026-01-09', 'shamim', '155'],
            ['Briton', 'Executive Office', 'patron', 300000, '2026-01-09', 'briton', '101'],
            ['Achieng Ruth', 'Finance', 'accountant', 120000, '2026-01-12', 'ruth', '122'],
            ['Bategeka', 'Credit', 'member', 300000, '2026-01-12', 'bategeka', '161'],
            ['Elisha', 'Agency Banking', 'member', 200000, '2026-01-15', 'elisha', '171'],
            ['Tumusiime Edgar', 'IT', 'member', 200000, '2026-01-15', 'edgar', '181'],
            ['Babirye Sarah', 'Customer Service', 'member', 100000, '2026-02-02', 'sarah', '156'],
            ['Kato Isaac', 'Operations', 'member', 200000, '2026-02-02', 'isaac', '105'],
            ['Nabirye Esther', 'Credit', 'member', 100000, '2026-03-03', 'esther', '162'],
            ['Wasswa Peter', 'Agency Banking', 'member', 150000, '2026-03-03', 'peter', '172'],
            ['Akello Grace', 'Marketing', 'member', 70000, '2026-04-06', 'grace', '143'],
            ['Byaruhanga Moses', 'Operations', 'member', 100000, '2026-01-12', 'moses', '106'],
            ['Nalwoga Brenda', 'Customer Service', 'member', 50000, '2026-01-15', 'brenda', '157'],
            ['Opio Daniel', 'IT', 'member', 250000, '2026-02-02', 'daniel', '182'],
            ['Mutebi Ivan', 'Operations', 'member', 100000, '2026-01-15', 'ivan', '107'],
        ];

        foreach ($rows as $i => $r) {
            $code = 'PEG-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

            $this->members[$code] = [
                'code' => $code,
                'name' => $r[0],
                'dept' => $r[1],
                'role' => $r[2],
                'monthly' => $r[3],
                'joined' => $r[4],
                'username' => $r[5],
                'ext' => $r[6],
                'status' => 'Active',
                'exitDate' => null,
                'exiting' => false,
                'exitNote' => null,
            ];
        }
    }

    private function seedDeposits(): void
    {
        foreach ($this->members as $member) {
            $joinedMonth = substr($member['joined'], 0, 7);

            foreach ($this->months as $ym) {
                if ($ym < $joinedMonth) {
                    continue;
                }

                $exit = self::EXIT_PLAN[$member['code']] ?? null;
                if ($exit && $ym > substr($exit[0], 0, 7)) {
                    continue;
                }

                $amount = $member['monthly'];
                $x = $this->rng->next();
                if ($x < 0.12) {
                    $amount += 50000;
                } elseif ($x < 0.16) {
                    $amount += 100000;
                }

                $channel = $this->channel();
                $day = $channel === 'Salary deduction' ? 25 : 20 + (int) floor($this->rng->next() * 8);
                $date = $ym.'-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                $ref = $this->reference($channel, $ym);

                $this->mk([
                    'type' => 'deposit',
                    'date' => $date,
                    'amount' => $amount,
                    'member' => $member['code'],
                    'meta' => ['channel' => $channel, 'ref' => $ref, 'month' => $ym],
                    'checkDate' => PegDates::addDays($date, 1),
                ]);
            }
        }
    }

    private function seedNovember(): void
    {
        // Four members who have already paid this month, and three whose payment is still waiting
        // to be checked — the Treasurer's desk needs both to be worth looking at.
        foreach ([['PEG-001', '2026-11-01'], ['PEG-006', '2026-11-02'], ['PEG-007', '2026-11-02'], ['PEG-008', '2026-11-03']] as [$code, $date]) {
            $ref = $this->reference('Mobile money', '2026-11');

            $this->mk([
                'type' => 'deposit',
                'date' => $date,
                'amount' => $this->members[$code]['monthly'],
                'member' => $code,
                'meta' => ['channel' => 'Mobile money', 'ref' => $ref, 'month' => '2026-11'],
                'checkDate' => '2026-11-03',
            ]);
        }

        foreach ([['PEG-003', '2026-11-02'], ['PEG-018', '2026-11-03'], ['PEG-010', '2026-11-03']] as [$code, $date]) {
            $channel = $code === 'PEG-003' ? 'Bank deposit' : 'Mobile money';
            $ref = $this->reference($channel, '2026-11');

            $this->mk([
                'type' => 'deposit',
                'date' => $date,
                'amount' => $this->members[$code]['monthly'],
                'member' => $code,
                'status' => 'pending_chair',
                'meta' => ['channel' => $channel, 'ref' => $ref, 'month' => '2026-11'],
            ]);
        }
    }

    private function seedLoans(): void
    {
        $this->seedLoan('PEG-008', 2000000, 6, '2026-04-10', 6, 'School fees', ['PEG-009']);
        $this->seedLoan('PEG-019', 700000, 4, '2026-04-12', 1, 'Medical');
        $this->seedLoan('PEG-012', 1500000, 4, '2026-05-05', 4, 'Business');
        $this->seedLoan('PEG-009', 3000000, 6, '2026-06-15', 4, 'Rent');
        $this->seedLoan('PEG-011', 1000000, 3, '2026-06-20', 1, 'Emergency');
        $this->seedLoan('PEG-014', 1800000, 6, '2026-07-01', 2, 'Asset purchase: motorcycle');
        $this->seedLoan('PEG-010', 4000000, 6, '2026-08-10', 2, 'Development: plot fencing');
        $this->seedLoan('PEG-015', 800000, 3, '2026-09-05', 1, 'School fees');
        $this->seedLoan('PEG-018', 6000000, 6, '2026-10-01', 0, 'Development: house finishing', ['PEG-010', 'PEG-012']);
    }

    private function seedLoan(string $code, int $amount, int $months, string $disbursed, int $paid, string $purpose, array $guarantors = []): void
    {
        $ref = $this->nextLoanRef();

        $this->loans[$ref] = [
            'ref' => $ref,
            'member' => $code,
            'amount' => $amount,
            'months' => $months,
            'rate' => 3,
            'method' => 'flat',
            'purpose' => $purpose,
            'guarantors' => $guarantors,
            'requestedAt' => PegDates::addDays($disbursed, -6).'T'.$this->clock(9, 16),
            'requestedBy' => $code,
            'status' => 'active',
            'disbursedOn' => $disbursed,
            'appraisal' => [
                'by' => self::TREASURER,
                'at' => PegDates::addDays($disbursed, -4).'T'.$this->clock(9, 11),
                'note' => 'Within policy. Recommended.',
                'decision' => 'recommended',
            ],
        ];

        $this->mk([
            'type' => 'loan_booking',
            'date' => $disbursed,
            'amount' => $amount,
            'member' => $code,
            'loan' => $ref,
            'escalate' => $amount > self::PATRON_THRESHOLD,
            'meta' => ['months' => $months, 'rate' => 3, 'method' => 'flat', 'purpose' => $purpose],
            'makeDate' => PegDates::addDays($disbursed, -4),
            'checkDate' => PegDates::addDays($disbursed, -2),
            'patronDate' => PegDates::addDays($disbursed, -1),
        ]);

        $schedule = LoanSchedule::build($amount, 3.0, $months, 'flat', $disbursed);

        for ($i = 0; $i < $paid; $i++) {
            $instalment = $schedule[$i];
            $date = PegDates::addDays($instalment['due'], -(int) floor($this->rng->next() * 3));

            $this->mk([
                'type' => 'repayment',
                'date' => $date,
                'amount' => $instalment['total'],
                'member' => $code,
                'loan' => $ref,
                'meta' => ['channel' => 'Salary deduction', 'ref' => 'SD-'.str_replace('-', '', $date)],
                'checkDate' => PegDates::addDays($date, 1),
            ]);
        }
    }

    private function seedPipeline(): void
    {
        $this->pushLoan('LN-010', 'PEG-013', 1200000, 4, 'School fees: Term 1', ['PEG-012'], '2026-10-30T09:12', [
            'status' => 'in_approval',
            'appraisal' => ['by' => self::TREASURER, 'at' => '2026-11-02T10:05', 'note' => 'Within policy. Recommended.', 'decision' => 'recommended'],
        ]);

        $this->mk([
            'type' => 'loan_booking',
            'date' => '2026-11-02',
            'amount' => 1200000,
            'member' => 'PEG-013',
            'loan' => 'LN-010',
            'status' => 'pending_chair',
            'meta' => ['months' => 4, 'rate' => 3, 'method' => 'flat', 'purpose' => 'School fees: Term 1'],
        ]);

        $this->pushLoan('LN-011', 'PEG-004', 7000000, 6, "Business: stock for spouse's shop", ['PEG-006'], '2026-10-29T15:30', [
            'status' => 'in_approval',
            'appraisal' => ['by' => self::TREASURER, 'at' => '2026-11-01T09:40', 'note' => 'Within 3× savings. Above the Patron threshold.', 'decision' => 'recommended'],
        ]);

        $this->mk([
            'type' => 'loan_booking',
            'date' => '2026-11-01',
            'amount' => 7000000,
            'member' => 'PEG-004',
            'loan' => 'LN-011',
            'status' => 'pending_patron',
            'escalate' => true,
            'meta' => ['months' => 6, 'rate' => 3, 'method' => 'flat', 'purpose' => "Business: stock for spouse's shop"],
            'checkDate' => '2026-11-02',
        ]);

        $this->pushLoan('LN-012', 'PEG-003', 1500000, 4, 'Rent', [], '2026-11-01T14:20', ['status' => 'requested']);
        $this->pushLoan('LN-013', 'PEG-012', 2000000, 4, 'Business: restock', ['PEG-008'], '2026-11-03T08:47', ['status' => 'requested']);

        // A repayment the Accountant has queried: the checker sees the note before deciding.
        $note = $this->mk([
            'type' => 'repayment',
            'date' => '2026-11-02',
            'amount' => 300000,
            'member' => 'PEG-014',
            'loan' => 'LN-006',
            'status' => 'pending_chair',
            'meta' => ['channel' => 'Mobile money', 'ref' => 'MM-73120'],
        ]);

        $this->txns[$note]['notes'][] = [
            'user' => 'PEG-007',
            'role' => 'accountant',
            'at' => '2026-11-02T15:40',
            'text' => 'Please confirm this reference against the mobile money statement before checking.',
        ];
    }

    private function pushLoan(string $ref, string $code, int $amount, int $months, string $purpose, array $guarantors, string $requestedAt, array $extra): void
    {
        $this->loans[$ref] = array_merge([
            'ref' => $ref,
            'member' => $code,
            'amount' => $amount,
            'months' => $months,
            'rate' => 3,
            'method' => 'flat',
            'purpose' => $purpose,
            'guarantors' => $guarantors,
            'requestedAt' => $requestedAt,
            'requestedBy' => $code,
            'status' => 'requested',
            'disbursedOn' => null,
            'appraisal' => null,
        ], $extra);

        $this->seqLn = max($this->seqLn, (int) substr($ref, 3));
    }

    private function seedExternal(): void
    {
        $this->mk([
            'type' => 'ext_loan',
            'date' => '2026-09-25',
            'amount' => 3000000,
            'ext' => 'EXT-001',
            'escalate' => true,
            'meta' => ['lender' => 'PlusSave Staff Welfare Facility', 'rate' => 1.5, 'months' => 6, 'purpose' => 'Meet October loan demand'],
            'makeDate' => '2026-09-21',
            'checkDate' => '2026-09-22',
            'patronDate' => '2026-09-24',
        ]);

        $this->mk([
            'type' => 'ext_repayment',
            'date' => '2026-10-24',
            'amount' => 545000,
            'ext' => 'EXT-001',
            'meta' => ['principal' => 500000, 'interest' => 45000, 'ref' => 'BK-5521087'],
            'checkDate' => '2026-10-26',
        ]);
    }

    private function seedPostingsAndExits(): void
    {
        foreach ($this->months as $ym) {
            $posting = $this->computePosting($ym);

            if ($posting['pool'] > 0) {
                $end = PegDates::monthEnd($ym);
                $alloc = $this->allocate($posting['pool'], $posting['bases']);
                $total = array_sum(array_column($alloc, 'amount'));

                $this->mk([
                    'type' => 'interest_posting',
                    'date' => $end,
                    'amount' => $total,
                    'meta' => [
                        'month' => $ym,
                        'income' => $posting['income'],
                        'external_interest' => $posting['external_interest'],
                        'share' => self::DIST_SHARE,
                        'pool' => $posting['pool'],
                        'alloc' => $alloc,
                    ],
                    'makeDate' => $end,
                    'checkDate' => PegDates::addDays($end, 1),
                ]);
            }

            foreach (self::EXIT_PLAN as $code => $exit) {
                if (substr($exit[0], 0, 7) !== $ym) {
                    continue;
                }

                $settlementDate = PegDates::addDays($exit[0], 1);
                $savings = $this->savingsAt($code, $exit[0]);
                $interest = $this->interestAt($code, $exit[0]);

                $this->mk([
                    'type' => 'exit_settlement',
                    'date' => $settlementDate,
                    'amount' => $savings + $interest,
                    'member' => $code,
                    'escalate' => true,
                    'meta' => [
                        'savings' => $savings,
                        'interest' => $interest,
                        'offset' => 0,
                        'loan_id' => null,
                        'net' => $savings + $interest,
                        'exit_date' => $exit[0],
                        'reason' => $exit[1],
                    ],
                    'makeDate' => $settlementDate,
                    'checkDate' => $settlementDate,
                    'patronDate' => PegDates::addDays($settlementDate, 1),
                ]);

                $this->members[$code]['status'] = 'Left';
                $this->members[$code]['exitDate'] = $exit[0];
            }
        }
    }

    private function seedRegistration(): void
    {
        $this->mk([
            'type' => 'membership',
            'date' => '2026-11-03',
            'amount' => 100000,
            'status' => 'pending_chair',
            'maker' => 'PEG-005',
            'makerRole' => 'mobiliser',
            'meta' => [
                'name' => 'Ainomugisha Clare',
                'dept' => 'Credit',
                'ext' => '163',
                'monthly' => 100000,
                'joined' => '2026-11-03',
                'prospect_id' => 'PR-001',
            ],
        ]);
    }

    /* --------------------------------------------------------- derived figures */

    /**
     * What the month earned, before it becomes a posting. Reuses the live ledger so the seeded
     * postings agree with the arrears and interest figures the screens later derive from the same
     * rows — if they were worked out separately here, a reviewer would see two answers.
     */
    private function computePosting(string $ym): array
    {
        $end = PegDates::monthEnd($ym);

        $income = 0;
        foreach ($this->loans as $loan) {
            if (! $loan['disbursedOn']) {
                continue;
            }

            foreach ($this->ledger($loan, $end)['splits'] as $split) {
                if (substr($split['date'], 0, 7) === $ym) {
                    $income += $split['interest'];
                }
            }
        }

        $externalInterest = 0;
        foreach ($this->txns as $txn) {
            if ($txn['type'] === 'ext_repayment' && $txn['status'] === 'posted' && substr($txn['date'], 0, 7) === $ym) {
                $externalInterest += $txn['meta']['interest'];
            }
        }

        $net = max(0, $income - $externalInterest);

        $bases = [];
        foreach ($this->members as $code => $member) {
            $base = $this->savingsAt($code, $end);
            if ($base > 0) {
                $bases[] = ['member' => $code, 'base' => $base];
            }
        }

        return [
            'income' => $income,
            'external_interest' => $externalInterest,
            'net' => $net,
            'pool' => (int) floor($net * self::DIST_SHARE / 100),
            'bases' => $bases,
        ];
    }

    /** @return array<int, array{member: string, base: int, amount: int}> */
    private function allocate(int $pool, array $bases): array
    {
        $total = array_sum(array_column($bases, 'base'));

        if ($total <= 0 || $pool <= 0) {
            return [];
        }

        $rows = [];
        foreach ($bases as $base) {
            $amount = (int) floor($pool * $base['base'] / $total);
            if ($amount > 0) {
                $rows[] = ['member' => $base['member'], 'base' => $base['base'], 'amount' => $amount];
            }
        }

        return $rows;
    }

    private function savingsAt(string $code, string $cutOff): int
    {
        $balance = 0;

        foreach ($this->txns as $txn) {
            if ($txn['status'] !== 'posted' || $txn['date'] > $cutOff || $txn['member'] !== $code) {
                continue;
            }

            if ($txn['type'] === 'deposit') {
                $balance += $txn['amount'];
            } elseif ($txn['type'] === 'exit_settlement') {
                $balance -= $txn['meta']['savings'];
            }
        }

        return $balance;
    }

    private function interestAt(string $code, string $cutOff): int
    {
        $balance = 0;

        foreach ($this->txns as $txn) {
            if ($txn['status'] !== 'posted' || $txn['date'] > $cutOff) {
                continue;
            }

            if ($txn['type'] === 'interest_posting' || $txn['type'] === 'interest_payout') {
                foreach ($txn['meta']['alloc'] as $row) {
                    if ($row['member'] === $code) {
                        $balance += $txn['type'] === 'interest_posting' ? $row['amount'] : -$row['amount'];
                    }
                }
            } elseif ($txn['type'] === 'exit_settlement' && $txn['member'] === $code) {
                $balance -= $txn['meta']['interest'];
            }
        }

        return $balance;
    }

    private function ledger(array $loan, string $asOf): array
    {
        $model = new Loan([
            'amount' => $loan['amount'],
            'rate' => $loan['rate'],
            'months' => $loan['months'],
            'method' => $loan['method'],
            'disbursed_on' => $loan['disbursedOn'],
        ]);

        $repayments = collect($this->txns)
            ->filter(fn (array $t) => $t['type'] === 'repayment' && $t['loan'] === $loan['ref'] && $t['status'] === 'posted')
            ->sortBy([['date', 'asc'], ['tmp', 'asc']])
            ->map(fn (array $t) => (object) ['ref' => 'tmp'.$t['tmp'], 'amount' => $t['amount'], 'value_date' => $t['date']])
            ->values();

        return LoanLedger::apply(
            $model,
            LoanSchedule::build((int) $loan['amount'], (float) $loan['rate'], (int) $loan['months'], $loan['method'], $loan['disbursedOn']),
            $repayments,
            $asOf
        );
    }

    /* ------------------------------------------------------------------ draws */

    private function clock(int $fromHour, int $toHour): string
    {
        $hour = $fromHour + (int) floor($this->rng->next() * ($toHour - $fromHour + 1));
        $minute = (int) floor($this->rng->next() * 60);

        return str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
    }

    private function channel(): string
    {
        $x = $this->rng->next();

        return $x < 0.55 ? 'Salary deduction' : ($x < 0.85 ? 'Mobile money' : 'Bank deposit');
    }

    private function reference(string $channel, string $ym): string
    {
        return match ($channel) {
            'Mobile money' => 'MM-'.(10000 + (int) floor($this->rng->next() * 89999)),
            'Bank deposit' => 'BK-'.(1000000 + (int) floor($this->rng->next() * 8999999)),
            default => 'SD-'.str_replace('-', '', $ym),
        };
    }

    private function nextLoanRef(): string
    {
        $this->seqLn++;

        return 'LN-'.str_pad((string) $this->seqLn, 3, '0', STR_PAD_LEFT);
    }

    /**
     * One entry as the prototype writes it: made in the morning by the Treasurer, checked after
     * lunch by the Chairperson, authorised in the afternoon by the Patron when it had to go that far.
     *
     * @return int the index of the new entry, so a note can be attached to it
     */
    private function mk(array $o): int
    {
        $status = $o['status'] ?? 'posted';
        $escalate = (bool) ($o['escalate'] ?? false);

        $index = count($this->txns);

        $this->txns[] = [
            'tmp' => $index,
            'type' => $o['type'],
            'date' => $o['date'],
            'amount' => $o['amount'],
            'member' => $o['member'] ?? null,
            'loan' => $o['loan'] ?? null,
            'ext' => $o['ext'] ?? null,
            'meta' => $o['meta'] ?? [],
            'escalate' => $escalate,
            'status' => $status,
            'maker' => $o['maker'] ?? self::TREASURER,
            'makerRole' => $o['makerRole'] ?? 'treasurer',
            'madeAt' => ($o['makeDate'] ?? $o['date']).'T'.$this->clock(8, 11),
            'checks' => [],
            'notes' => [],
        ];

        $checkedOn = $o['checkDate'] ?? $o['date'];

        if ($status === 'posted' || $status === 'pending_patron') {
            $this->txns[$index]['checks'][] = [
                'user' => self::CHAIR,
                'role' => 'chair',
                'action' => 'approved',
                'at' => $checkedOn.'T'.$this->clock(12, 14),
                'comment' => '',
            ];
        }

        if ($status === 'posted' && $escalate) {
            $this->txns[$index]['checks'][] = [
                'user' => self::PATRON,
                'role' => 'patron',
                'action' => 'authorised',
                'at' => ($o['patronDate'] ?? $checkedOn).'T'.$this->clock(15, 17),
                'comment' => '',
            ];
        }

        return $index;
    }

    /* ---------------------------------------------------------------- persist */

    private function persist(): void
    {
        $memberIds = [];
        $userIds = [];

        foreach ($this->members as $member) {
            $row = Member::create([
                'code' => $member['code'],
                'name' => $member['name'],
                'department' => $member['dept'],
                'monthly_pledge' => $member['monthly'],
                'joined_on' => $member['joined'],
                'extension' => $member['ext'],
                'status' => $member['status'] === 'Left' ? 'left' : 'active',
                'exit_on' => $member['exitDate'],
                'exit_flagged' => $member['exiting'],
                'exit_note' => $member['exitNote'],
            ]);

            $memberIds[$member['code']] = $row->id;

            $user = User::create([
                'name' => $member['name'],
                'username' => $member['username'],
                'password' => 'peg2026',
                'member_id' => $row->id,
                'status' => 'active',
            ]);

            $user->assignRole($member['role']);
            $userIds[$member['code']] = $user->id;
        }

        $admin = User::create([
            'name' => 'Marvin Bategeka',
            'username' => 'marvin',
            'password' => 'peg2026',
            'member_id' => null,
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $external = ExternalLoan::create([
            'ref' => 'EXT-001',
            'lender' => 'PlusSave Staff Welfare Facility',
            'amount' => 3000000,
            'rate' => 1.5,
            'months' => 6,
            'purpose' => 'Meet October loan demand',
            'status' => 'active',
            'received_on' => '2026-09-25',
        ]);

        $loanIds = [];
        foreach ($this->loans as $ref => $loan) {
            $row = Loan::create([
                'ref' => $ref,
                'member_id' => $memberIds[$loan['member']],
                'amount' => $loan['amount'],
                'months' => $loan['months'],
                'rate' => $loan['rate'],
                'method' => $loan['method'],
                'purpose' => $loan['purpose'],
                'status' => $loan['status'],
                'requested_by' => $userIds[$loan['requestedBy']],
                'requested_at' => $loan['requestedAt'],
                'disbursed_on' => $loan['disbursedOn'],
                'appraised_by' => $loan['appraisal'] ? $userIds[$loan['appraisal']['by']] : null,
                'appraised_at' => $loan['appraisal']['at'] ?? null,
                'appraisal_decision' => $loan['appraisal']['decision'] ?? null,
                'appraisal_note' => $loan['appraisal']['note'] ?? null,
            ]);

            $loanIds[$ref] = $row->id;

            foreach ($loan['guarantors'] as $guarantor) {
                $row->guarantors()->attach($memberIds[$guarantor]);
            }
        }

        // The prototype hands out entry numbers in the order they were typed, not the order they
        // were typed about, so the books read straight through.
        $ordered = collect($this->txns)->sortBy('madeAt')->values();

        $n = 0;
        foreach ($ordered as $txn) {
            $n++;

            $meta = $txn['meta'];
            $alloc = $meta['alloc'] ?? null;
            if ($alloc !== null) {
                $meta['alloc'] = array_map(fn (array $row) => [
                    'member_id' => $memberIds[$row['member']],
                    'base' => $row['base'],
                    'amount' => $row['amount'],
                ], $alloc);
            }

            $row = Transaction::create([
                'ref' => 'TX-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'type' => $txn['type'],
                'value_date' => $txn['date'],
                'amount' => $txn['amount'],
                'member_id' => $txn['member'] ? $memberIds[$txn['member']] : null,
                'loan_id' => $txn['loan'] ? $loanIds[$txn['loan']] : null,
                'external_loan_id' => $txn['ext'] ? $external->id : null,
                'meta' => $meta,
                'escalated' => $txn['escalate'],
                'status' => $txn['status'],
                'maker_id' => $userIds[$txn['maker']],
                'maker_role' => $txn['makerRole'],
                'made_at' => $txn['madeAt'],
            ]);

            foreach ($txn['checks'] as $check) {
                $row->checks()->create([
                    'user_id' => $userIds[$check['user']],
                    'role' => $check['role'],
                    'action' => $check['action'],
                    'comment' => $check['comment'] ?: null,
                    'checked_at' => $check['at'],
                ]);
            }

            foreach ($txn['notes'] as $note) {
                $row->notes()->create([
                    'user_id' => $userIds[$note['user']],
                    'role' => $note['role'],
                    'text' => $note['text'],
                    'noted_at' => $note['at'],
                ]);
            }

            foreach ($alloc ?? [] as $share) {
                InterestAllocation::create([
                    'transaction_id' => $row->id,
                    'member_id' => $memberIds[$share['member']],
                    'base' => $share['base'],
                    'amount' => $share['amount'],
                ]);
            }
        }

        $this->persistGroupRecords($memberIds, $userIds);
    }

    private function persistGroupRecords(array $memberIds, array $userIds): void
    {
        $events = [
            ['2026-01-30', 'PEG inaugural general meeting', 'general', 'Adopted the PEG constitution and elected the committee.'],
            ['2026-02-13', 'PEG launch and first contributions', 'activity', ''],
            ['2026-03-27', 'Q1 general meeting', 'general', 'Savings review and loan policy.'],
            ['2026-04-18', 'Financial literacy session: budgeting', 'activity', 'Facilitated by the Finance team.'],
            ['2026-06-26', 'Q2 general meeting', 'general', ''],
            ['2026-07-17', 'Mid-year review and loan clinic', 'activity', 'One-on-one loan advice for members.'],
            ['2026-08-29', 'PEG team building day', 'activity', ''],
            ['2026-09-25', 'Q3 general meeting', 'general', 'Approved the external facility of UGX 3,000,000.'],
            ['2026-10-10', 'Internal review of PEG books', 'finance', 'Accountant with the internal audit team.'],
            ['2026-11-20', 'Loan policy review workshop', 'meeting', 'Review the rate, tenure bands and loan limits.'],
            ['2026-12-11', 'Annual interest payout', 'finance', 'Interest only. Principal stays in PEG.'],
            ['2026-12-11', 'Annual general meeting', 'general', 'Annual report, accounts and committee report.'],
            ['2026-12-18', 'PEG end-of-year get-together', 'activity', ''],
            ['2027-01-29', 'Annual planning session 2027', 'meeting', 'Set the 2027 PEG calendar and targets.'],
            ['2027-03-26', 'Q1 general meeting', 'general', ''],
        ];

        foreach ($events as $i => $event) {
            CalendarEvent::create([
                'ref' => 'EV-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'date' => $event[0],
                'title' => $event[1],
                'type' => $event[2],
                'notes' => $event[3],
                'created_by' => $userIds['PEG-003'],
            ]);
        }

        $notices = [
            ['NT-003', 'November contributions due by 28 November', 'Save at least UGX 50,000 through salary deduction, mobile money or a bank deposit. If you pay directly, use "Notify a payment" so the Treasurer can record it.', true, '2026-11-01'],
            ['NT-002', 'Annual interest payout on 11 December', 'Your accumulated interest for 2026 will be paid on 11 December. Your principal savings stay in PEG and keep earning.', false, '2026-10-28'],
            ['NT-001', 'Loan policy review workshop on 20 November', 'The committee will review the loan rate, tenure bands and loan limits. Send your suggestions to the Secretary before the workshop.', false, '2026-10-20'],
        ];

        foreach ($notices as $notice) {
            Notice::create([
                'ref' => $notice[0],
                'title' => $notice[1],
                'body' => $notice[2],
                'pinned' => $notice[3],
                'posted_on' => $notice[4],
                'author_id' => $userIds['PEG-004'],
                'author_role' => 'spokesman',
            ]);
        }

        $minutes = [
            ['MN-003', 'Committee meeting, October 2026', '2026-10-02', 'Committee', '7 of 7 committee members',
                'Reviewed September postings, two loans in arrears and the external facility.',
                ['Mobiliser to follow up members in arrears and log each contact', 'Annual interest payout set for 11 December 2026', 'Loan policy review workshop on 20 November']],
            ['MN-002', 'Q3 general meeting', '2026-09-25', 'General', '14 members present',
                'Members reviewed savings growth and loan demand for October.',
                ['Approved external borrowing of UGX 3,000,000 from the PlusSave Staff Welfare Facility', 'Interest sharing kept at 90% of net loan interest']],
            ['MN-001', 'Committee meeting, September 2026', '2026-09-04', 'Committee', '6 of 7 committee members',
                'Received the August cash book and one exit settlement.',
                ['Treasurer to prepare the external borrowing proposal', 'Secretary to update the members register']],
        ];

        foreach ($minutes as $minute) {
            Minute::create([
                'ref' => $minute[0],
                'title' => $minute[1],
                'date' => $minute[2],
                'kind' => $minute[3],
                'attendance' => $minute[4],
                'summary' => $minute[5],
                'decisions' => $minute[6],
                'created_by' => $userIds['PEG-003'],
            ]);
        }

        $prospects = [
            ['PR-001', 'Ainomugisha Clare', 'Credit', 'Registered', 'Registration sent for approval'],
            ['PR-002', 'Lubega Henry', 'Finance', 'Interested', 'Wants to start in December'],
            ['PR-003', 'Kyomuhendo Judith', 'HR & Admin', 'Approached', ''],
            ['PR-004', 'Ssali Martin', 'Agency Banking', 'Interested', 'Asked about loan limits'],
        ];

        foreach ($prospects as $prospect) {
            Prospect::create([
                'ref' => $prospect[0],
                'name' => $prospect[1],
                'department' => $prospect[2],
                'stage' => $prospect[3],
                'note' => $prospect[4],
                'created_by' => $userIds['PEG-005'],
            ]);
        }

        Reminder::create([
            'member_id' => $memberIds['PEG-011'],
            'month' => '2026-11',
            'created_by' => $userIds['PEG-005'],
            'created_at' => '2026-11-02 09:30:00',
            'updated_at' => '2026-11-02 09:30:00',
        ]);

        $payNotices = [
            ['PN-001', 'PEG-012', 200000, '2026-11-03', 'Mobile money', 'MM-58213', '2026-11', '2026-11-03 08:52:00'],
            ['PN-002', 'PEG-009', 200000, '2026-11-02', 'Bank deposit', 'BK-4410921', '2026-11', '2026-11-02 17:05:00'],
        ];

        foreach ($payNotices as $notice) {
            PaymentNotice::create([
                'ref' => $notice[0],
                'member_id' => $memberIds[$notice[1]],
                'amount' => $notice[2],
                'paid_on' => $notice[3],
                'channel' => $notice[4],
                'reference' => $notice[5],
                'month' => $notice[6],
                'status' => 'open',
                'created_at' => $notice[7],
                'updated_at' => $notice[7],
            ]);
        }

        $audit = [
            ['2026-10-02 16:10:00', 'PEG-003', 'secretary', 'Recorded minutes "Committee meeting, October 2026"', 'MN-003'],
            ['2026-11-01 08:10:00', 'PEG-004', 'spokesman', 'Published notice "November contributions due by 28 November"', 'NT-003'],
            ['2026-11-02 09:30:00', 'PEG-005', 'mobiliser', 'Logged a contribution reminder for Babirye Sarah', 'PEG-011'],
            ['2026-11-02 11:20:00', 'PEG-003', 'secretary', 'Recorded exit notice for Bategeka', 'PEG-008'],
        ];

        foreach ($audit as $row) {
            ActivityLog::create([
                'user_id' => $userIds[$row[1]],
                'name' => $this->members[$row[1]]['name'],
                'role' => $row[2],
                'action' => $row[3],
                'subject' => $row[4],
                'created_at' => $row[0],
                'updated_at' => $row[0],
            ]);
        }
    }
}
