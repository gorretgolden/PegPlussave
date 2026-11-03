<?php

namespace App\Http\Controllers;

use App\Models\InterestAllocation;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Support\Format;
use App\Support\Policy;
use App\Support\Trail;
use App\Support\TreasuryPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The seven questions the committee is asked about the group, each answered from the same posted
 * entries. Nothing here is stored: a report is a way of reading the book, so it cannot drift from it.
 */
class ReportController extends Controller
{
    public const TABS = ['par', 'disbursed', 'arrears', 'funds', 'interest', 'contrib', 'dept'];

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'par';
        $group = TreasuryPosition::group();
        $year = $this->year($request);
        $report = $this->report($tab, $group, $year);

        return view('pages.reports', array_merge([
            'page' => 'reports',
            'tab' => $tab,
            'year' => $year,
            'years' => $this->years(),
            'group' => $group,
            'isTreasurer' => $request->user()->can('make-treasury-entry'),
            'settings' => Policy::all(),
            'tabs' => [
                'par' => 'Portfolio at risk',
                'disbursed' => 'Loans disbursed',
                'arrears' => 'Arrears',
                'funds' => 'Available funds',
                'interest' => "Savers' interest",
                'contrib' => 'Contributions',
                'dept' => 'By department',
            ],
        ], $report, [
            'csv' => $this->csv($tab, $group, $year, $report),
            'members' => $this->membersFor($tab, $group, $report),
            'dialogs' => $this->ledgersFor($tab, $group, $year, $report),
        ]));
    }

    /**
     * The boxes a report row opens. Each table is drawn from the ledgers and balances that are
     * already in hand, so a report of forty loans costs one set of queries, not forty.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ledgersFor(string $tab, array $group, string $year, array $report): array
    {
        return match ($tab) {
            'par' => $report['risk']->all(),
            'arrears' => $report['late']->all(),
            'disbursed' => array_values(array_filter(
                $group['ledgers'],
                fn (array $l) => $l['loan']->disbursed_on && $l['loan']->disbursed_on->format('Y') === $year
            )),
            default => [],
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Member>
     */
    private function membersFor(string $tab, array $group, array $report): \Illuminate\Support\Collection
    {
        return match ($tab) {
            'interest' => $report['members'],
            'contrib' => $report['grid']->pluck('member'),
            default => collect(),
        };
    }

    public function excel(Request $request, string $report)
    {
        abort_unless(in_array($report, self::TABS, true), 404);

        $group = TreasuryPosition::group();
        $year = $this->year($request);
        $rows = $this->csv($report, $group, $year, $this->report($report, $group, $year));
        $filename = 'peg-'.$report.'-'.now()->toDateString().'.csv';

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function report(string $tab, array $group, string $year): array
    {
        return match ($tab) {
            'disbursed' => $this->disbursed($group, $year),
            'arrears' => $this->arrears($group),
            'funds' => $this->funds($group, $year),
            'interest' => $this->interest($group, $year),
            'contrib' => $this->contrib($group, $year),
            'dept' => $this->dept($group),
            default => $this->par($group),
        };
    }

    /* ------------------------------------------------------------------ portfolio at risk */

    private function par(array $group): array
    {
        $running = $group['running'];
        $total = array_sum(array_column($running, 'outstandingPrincipal'));

        $tiles = [];
        foreach ([['PAR 1', 1], ['PAR 30', 30], ['PAR 60', 60], ['PAR 90', 90]] as [$label, $over]) {
            $atRisk = array_sum(array_column(
                array_filter($running, fn (array $l) => $l['daysLate'] > $over),
                'outstandingPrincipal'
            ));
            $percent = $total > 0 ? $atRisk / $total * 100 : 0.0;

            $tiles[] = [
                'label' => $label,
                'percent' => $percent,
                'amount' => $atRisk,
                'tone' => $percent > 10 ? 'bad' : ($percent > 5 ? 'warn' : 'ok'),
            ];
        }

        $buckets = [];
        foreach (self::bands() as $band) {
            $loans = array_values(array_filter($running, $band['test']));
            $value = array_sum(array_column($loans, 'outstandingPrincipal'));

            $buckets[] = [
                'label' => $band['label'],
                'plain' => $band['plain'],
                'count' => count($loans),
                'value' => $value,
                'percent' => $total > 0 ? $value / $total * 100 : 0.0,
                'color' => $band['color'],
            ];
        }

        return [
            'total' => $total,
            'tiles' => $tiles,
            'buckets' => $buckets,
            'risk' => collect($running)
                ->sortByDesc('daysLate')
                ->map(fn (array $ledger) => array_merge($ledger, ['bucket' => self::band((int) $ledger['daysLate'])['label'] ?? '']))
                ->values(),
        ];
    }

    /**
     * @return array<int, array{label: string, plain: string, test: callable, color: string}>
     */
    private static function bands(): array
    {
        return [
            ['label' => 'Current', 'plain' => 'Current', 'test' => fn (array $l) => $l['daysLate'] === 0, 'color' => 'var(--ok)'],
            ['label' => '1–30 days', 'plain' => '1–30', 'test' => fn (array $l) => $l['daysLate'] >= 1 && $l['daysLate'] <= 30, 'color' => 'var(--warn)'],
            ['label' => '31–60 days', 'plain' => '31–60', 'test' => fn (array $l) => $l['daysLate'] > 30 && $l['daysLate'] <= 60, 'color' => 'var(--warn)'],
            ['label' => '61–90 days', 'plain' => '61–90', 'test' => fn (array $l) => $l['daysLate'] > 60 && $l['daysLate'] <= 90, 'color' => 'var(--bad)'],
            ['label' => 'Over 90 days', 'plain' => '90+', 'test' => fn (array $l) => $l['daysLate'] > 90, 'color' => 'var(--bad)'],
        ];
    }

    private static function band(int $days): ?array
    {
        foreach (self::bands() as $band) {
            if ($band['test'](['daysLate' => $days])) {
                return $band;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------ loans disbursed */

    private function disbursed(array $group, string $year): array
    {
        $loans = Loan::query()->with(['member', 'booking'])
            ->whereNotNull('disbursed_on')
            ->where('disbursed_on', 'like', $year.'-%')
            ->orderBy('disbursed_on')
            ->get();

        $total = (int) $loans->sum('amount');

        return [
            'loans' => $loans,
            'total' => $total,
            'chart' => collect(range(1, 12))
                ->map(fn (int $m) => [
                    'label' => self::MONTHS[$m - 1],
                    'a' => (int) $loans->filter(fn (Loan $l) => (int) $l->disbursed_on->format('n') === $m)->sum('amount'),
                ])
                ->all(),
            'average' => $loans->count() ? (int) round($total / $loans->count()) : 0,
            'largest' => (int) ($loans->max('amount') ?? 0),
            'states' => collect($group['ledgers'])->map(fn (array $l) => $l['state'])->all(),
        ];
    }

    /* ------------------------------------------------------------------ arrears */

    private function arrears(array $group): array
    {
        $list = collect($group['running'])
            ->filter(fn (array $l) => $l['daysLate'] > 0)
            ->sortByDesc('daysLate')
            ->map(fn (array $ledger) => array_merge(
                $ledger,
                ['bucket' => self::band((int) $ledger['daysLate'])['plain'] ?? '']
            ))
            ->values();

        return [
            'late' => $list,
            'lateAmount' => (int) $list->sum('arrears'),
            'atRisk' => (int) $list->sum('outstandingPrincipal'),
            'runningCount' => count($group['running']),
        ];
    }

    /* ------------------------------------------------------------------ available funds */

    private function funds(array $group, string $year): array
    {
        $movements = Transaction::query()->posted()
            ->whereIn('type', array_keys(Transaction::CASH_EFFECT))
            ->where('value_date', 'like', $year.'-%')
            ->get();

        $flows = $movements->groupBy('type')
            ->map(fn (Collection $rows, string $type) => (int) $rows->sum(
                fn (Transaction $t) => $t->amount * (Transaction::CASH_EFFECT[$type] ?? 0)
            ))
            ->filter(fn (int $value) => $value !== 0);

        return [
            'assets' => $group['cash'] + $group['outstanding_principal'],
            'liabilities' => $group['total_savings'] + $group['interest_due'] + $group['external_outstanding'],
            'flows' => $flows,
            'net' => (int) $movements->sum(
                fn (Transaction $t) => $t->amount * (Transaction::CASH_EFFECT[$t->type] ?? 0)
            ),
        ];
    }

    /* ------------------------------------------------------------------ savers' interest */

    private function interest(array $group, string $year): array
    {
        $postings = Transaction::query()->with('allocations')
            ->posted()
            ->where('type', Transaction::INTEREST_POSTING)
            ->where('meta->month', 'like', $year.'-%')
            ->get()
            ->sortBy(fn (Transaction $t) => (string) $t->meta['month'])
            ->values();

        $shares = $postings->mapWithKeys(fn (Transaction $t) => [
            $t->id => $t->allocations->mapWithKeys(fn (InterestAllocation $a) => [(int) $a->member_id => (int) $a->amount])->all(),
        ]);

        $members = Member::query()->with('user')->orderBy('name')->get()
            ->filter(fn (Member $m) => (int) ($group['balances'][$m->id]['interest_earned'] ?? 0) > 0
                || $m->status === 'active')
            ->values();

        $withdrawn = (int) array_sum(array_column($group['balances'], 'interest_paid_out'));
        $activeCount = max(1, $members->filter(fn (Member $m) => $m->status === 'active')->count());

        $columns = $postings->map(function (Transaction $t) {
            $month = (string) ($t->meta['month'] ?? '');

            return [
                'id' => $t->id,
                'ref' => $t->ref,
                'transaction' => $t,
                'month' => $month,
                'label' => self::MONTHS[(int) substr($month, 5, 2) - 1] ?? '',
                'amount' => (int) $t->amount,
                'savers' => $t->allocations->count(),
            ];
        })->values();

        return [
            'columns' => $columns,
            'shares' => $shares,
            'members' => $members,
            'distributed' => $group['distributed'],
            'withdrawn' => $withdrawn,
            'average' => (int) round($group['interest_due'] / $activeCount),
        ];
    }

    /* ------------------------------------------------------------------ contributions */

    private function contrib(array $group, string $year): array
    {
        $current = now()->format('Y-m');
        $minimum = (int) Policy::get('min_monthly');

        $pending = Transaction::query()
            ->where('type', Transaction::DEPOSIT)
            ->where('status', 'like', 'pending%')
            ->get(['member_id', 'meta'])
            ->groupBy('member_id')
            ->map(fn (Collection $rows) => $rows->pluck('meta')->pluck('month')->filter()->unique());

        $grid = Member::query()->with('user')->orderBy('name')->get()
            ->filter(fn (Member $m) => $m->joined_on->format('Y') <= $year)
            ->values()
            ->map(function (Member $member) use ($group, $year, $current, $minimum, $pending) {
                $row = $group['balances'][$member->id] ?? [];
                $joined = $member->joined_on->format('Y-m');
                $exit = $member->exit_on?->format('Y-m');
                $checking = $pending->get($member->id) ?? collect();
                $months = [];

                foreach (range(1, 12) as $i) {
                    $ym = $year.'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                    $saved = (int) ($row['deposits_by_month'][$ym] ?? 0);

                    // A month a member could not have saved in, either because they had not joined or
                    // because they had gone, is not a gap in the record. It is drawn blank so the
                    // reader counts only the months that were really owed.
                    [$state, $text] = match (true) {
                        $ym < $joined, $exit && $ym > $exit, $ym > $current => ['na', '·'],
                        $saved >= $minimum => ['met', Format::compact($saved)],
                        $saved > 0 => ['low', Format::compact($saved)],
                        $checking->contains($ym) => ['pend', 'Check'],
                        $ym === $current => ['na', 'Due'],
                        default => ['miss', 'None'],
                    };

                    $months[$ym] = ['state' => $state, 'text' => $text, 'amount' => $saved];
                }

                $savedThisMonth = (int) ($row['deposits_by_month'][$current] ?? 0) > 0;

                return [
                    'member' => $member,
                    'months' => $months,
                    'total' => (int) collect($row['deposits_by_month'] ?? [])
                        ->filter(fn ($value, $key) => str_starts_with((string) $key, $year))
                        ->sum(),
                    'inScope' => $member->status === 'active' && $joined <= $current,
                    'saved' => $savedThisMonth,
                    'checking' => ! $savedThisMonth && $checking->contains($current),
                ];
            });

        $inScope = $grid->filter(fn (array $r) => $r['inScope']);
        $savedCount = $inScope->filter(fn (array $r) => $r['saved'])->count();

        return [
            'grid' => $grid,
            'month' => self::MONTHS[(int) substr($current, 5, 2) - 1],
            'minimum' => $minimum,
            'deadline' => Format::ordinal((int) Policy::get('contribution_day')),
            'saved' => $savedCount,
            'ofActive' => $inScope->count(),
            'percent' => $inScope->count() > 0 ? (int) round($savedCount / $inScope->count() * 100) : 0,
            'checking' => $inScope->filter(fn (array $r) => $r['checking'])->count(),
            'yetToSave' => $inScope->count() - $savedCount - $inScope->filter(fn (array $r) => $r['checking'])->count(),
            'savedThisYear' => (int) $grid->sum('total'),
        ];
    }

    /* ------------------------------------------------------------------ by department */

    private function dept(array $group): array
    {
        $byMember = collect($group['ledgers'])
            ->filter(fn (array $l) => $l['loan']->disbursed_on && $l['balance'] > 0)
            ->groupBy(fn (array $l) => $l['loan']->member_id);

        $rows = [];

        foreach (Member::query()->orderBy('department')->get() as $member) {
            $dept = (string) $member->department;

            $row = $rows[$dept] ??= [
                'dept' => $dept,
                'active' => 0,
                'left' => 0,
                'savings' => 0,
                'loans' => 0,
                'outstanding' => 0,
                'inArrears' => 0,
            ];

            if ($member->status === 'active') {
                $row['active']++;
            } else {
                $row['left']++;
            }
            $row['savings'] += (int) ($group['balances'][$member->id]['balance'] ?? 0);

            foreach ($byMember->get($member->id, collect()) as $ledger) {
                $row['loans']++;
                $row['outstanding'] += (int) $ledger['outstandingPrincipal'];
                if ($ledger['daysLate'] > 0) {
                    $row['inArrears']++;
                }
            }

            $rows[$dept] = $row;
        }

        $rows = collect($rows)->sortByDesc('savings')->values()->all();

        return ['rows' => $rows, 'totalSavings' => array_sum(array_column($rows, 'savings'))];
    }

    /* ------------------------------------------------------------------ the file

       The download is built from the figures the screen just showed, so a row in the file cannot
       disagree with the row on the page. */

    /**
     * @param  array<string, mixed>  $data  the same report the screen rendered
     * @return array<int, array<int, string|int>>
     */
    private function csv(string $report, array $group, string $year, array $data): array
    {
        return match ($report) {
            'par' => $this->parCsv($group, $data),
            'disbursed' => $this->disbursedCsv($group, $data),
            'arrears' => $this->arrearsCsv($data),
            'funds' => $this->fundsCsv($group),
            'interest' => $this->interestCsv($group, $data),
            'contrib' => $this->contribCsv($year, $data),
            'dept' => $this->deptCsv($data),
        };
    }

    private function parCsv(array $group, array $data): array
    {
        return array_merge(
            [['Bucket', 'Loans', 'Outstanding principal (UGX)', 'Share of portfolio (%)']],
            array_map(fn (array $b) => [$b['label'], $b['count'], $b['value'], number_format($b['percent'], 2)], $data['buckets']),
            [['Total', count($group['running']), $data['total'], '100.00']]
        );
    }

    private function disbursedCsv(array $group, array $data): array
    {
        return array_merge(
            [['Disbursed', 'Loan', 'Member', 'Department', 'Amount (UGX)', 'Months', 'Purpose', 'Status']],
            $data['loans']
                ->map(fn (Loan $l) => [
                    $l->disbursed_on->toDateString(),
                    $l->ref,
                    $l->member->name,
                    $l->member->department,
                    $l->amount,
                    $l->months,
                    $l->purpose,
                    $data['states'][$l->id] ?? '',
                ])->values()->all()
        );
    }

    private function arrearsCsv(array $data): array
    {
        return array_merge(
            [['Loan', 'Member', 'Department', 'Extension', 'Missed installments', 'Arrears (UGX)', 'Days late', 'Bucket', 'Balance (UGX)']],
            $data['late']
                ->map(fn (array $l) => [
                    $l['loan']->ref,
                    $l['loan']->member->name,
                    $l['loan']->member->department,
                    (string) $l['loan']->member->extension,
                    $l['missed'],
                    $l['arrears'],
                    $l['daysLate'],
                    $l['bucket'],
                    $l['balance'],
                ])->values()->all()
        );
    }

    private function fundsCsv(array $group): array
    {
        return array_merge([['Line', 'UGX']], [
            ['Cash at bank (book)', $group['cash']],
            ['Less liquidity reserve', -$group['reserve']],
            ['Less loans in approval', -$group['committed']],
            ['Available for new loans', $group['available']],
            ['Assets: cash', $group['cash']],
            ['Assets: loans outstanding (principal)', $group['outstanding_principal']],
            ['Liabilities: members savings', $group['total_savings']],
            ['Liabilities: interest due to members', $group['interest_due']],
            ['Liabilities: external borrowing', $group['external_outstanding']],
            ['Reserves: retained income', $group['retained']],
        ]);
    }

    private function interestCsv(array $group, array $data): array
    {
        $head = ['Member', 'Status'];
        foreach ($data['columns'] as $column) {
            $head[] = Format::month($column['month']);
        }
        $head[] = 'Earned';
        $head[] = 'Withdrawn';
        $head[] = 'Balance';

        return array_merge([$head], $data['members']->map(function (Member $member) use ($data, $group) {
            $row = $group['balances'][$member->id] ?? [];
            $cells = $data['columns']->map(fn (array $column) => $data['shares'][$column['id']][$member->id] ?? 0)->all();

            return array_merge(
                [$member->name, ucfirst($member->status)],
                $cells,
                [
                    (int) ($row['interest_earned'] ?? 0),
                    (int) ($row['interest_paid_out'] ?? 0),
                    (int) ($row['interest_earned'] ?? 0) - (int) ($row['interest_paid_out'] ?? 0),
                ]
            );
        })->values()->all());
    }

    private function contribCsv(string $year, array $data): array
    {
        $head = ['Member', 'Status'];
        foreach (self::MONTHS as $month) {
            $head[] = $month.' '.$year;
        }
        $head[] = 'Total';

        return array_merge([$head], $data['grid']
            ->map(fn (array $r) => array_merge(
                [$r['member']->name, ucfirst($r['member']->status)],
                array_values(array_map(fn (array $cell) => $cell['amount'], $r['months'])),
                [$r['total']]
            ))
            ->values()->all());
    }

    private function deptCsv(array $data): array
    {
        return array_merge(
            [['Department', 'Active members', 'Left', 'Savings (UGX)', 'Running loans', 'Loans outstanding (UGX)', 'Loans in arrears']],
            array_map(fn (array $r) => [
                $r['dept'], $r['active'], $r['left'], $r['savings'], $r['loans'], $r['outstanding'], $r['inArrears'],
            ], $data['rows'])
        );
    }

    private function year(Request $request): string
    {
        $year = (string) $request->query('year', now()->format('Y'));

        return preg_match('/^20\d{2}$/', $year) ? $year : now()->format('Y');
    }

    /**
     * @return array<int, string>
     */
    private function years(): array
    {
        $first = (string) Member::query()->min('joined_on');

        return array_values(array_unique(array_map(
            fn (int $y) => (string) $y,
            range(now()->year, $first !== '' ? (int) substr($first, 0, 4) : now()->year)
        )));
    }
}
