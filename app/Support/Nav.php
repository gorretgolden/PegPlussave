<?php

namespace App\Support;

use App\Models\Loan;
use App\Models\PaymentNotice;
use App\Models\Transaction;
use App\Models\User;

/**
 * The menu, and the counts that sit on it. A page is only reachable if the account holds its
 * ability, so this is the one place that decides what a person can see; the routes ask the same
 * question and a view never decides on its own.
 */
class Nav
{
    /**
     * Each entry is [id, label, icon, ability, lock]. A locked desk is marked with a padlock because
     * it carries other people's money.
     */
    public const GROUPS = [
        'My PEG' => [
            ['dashboard', 'Dashboard', 'home', 'view-dashboard', false],
            ['savings', 'My savings', 'wallet', 'view-own-savings', false],
            ['loans', 'My loans', 'loan', 'view-own-loans', false],
            ['calendar', 'PEG calendar', 'cal', 'view-calendar', false],
            ['directory', 'Members directory', 'users', 'view-directory', false],
            ['notices', 'Notice board', 'mega', 'view-notices', false],
            ['minutes', 'Meeting minutes', 'notes', 'view-minutes', false],
        ],
        'Committee' => [
            ['approvals', 'Approvals', 'check', 'view-approvals', false],
            ['treasury', 'Treasury', 'vault', 'view-treasury', true],
            ['reports', 'Reports', 'chart', 'view-reports', true],
            ['ledger', 'Cash book', 'ledger', 'view-ledger', true],
            ['mobilise', 'Mobilisation', 'flag', 'view-mobilisation', false],
            ['audit', 'Audit trail', 'shield', 'view-audit', false],
        ],
        'System' => [
            ['admin', 'Users & settings', 'gear', 'manage-system', false],
        ],
    ];

    public static function forUser(User $user): array
    {
        $allowed = self::pageIds($user);

        return array_filter(array_map(
            fn (string $group, array $items) => [
                'group' => $group,
                'items' => array_values(array_filter($items, fn (array $item) => in_array($item[0], $allowed, true))),
            ],
            array_keys(self::GROUPS),
            self::GROUPS
        ), fn (array $group) => $group['items'] !== []);
    }

    /**
     * @return array<int, string>
     */
    public static function pageIds(User $user): array
    {
        return array_values(array_merge(...array_map(
            fn (array $items) => array_column(array_filter($items, fn (array $i) => $user->can($i[3])), 0),
            array_values(self::GROUPS)
        )));
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: bool}|null
     */
    public static function item(string $page): ?array
    {
        foreach (self::GROUPS as $items) {
            foreach ($items as $item) {
                if ($item[0] === $page) {
                    return $item;
                }
            }
        }

        return null;
    }

    public static function label(string $page): string
    {
        return self::item($page)[1] ?? 'Dashboard';
    }

    public static function locked(string $page): bool
    {
        return (bool) (self::item($page)[4] ?? false);
    }

    /**
     * How many things are waiting on this desk right now. Only the two that a person can act on:
     * a badge that counts something they cannot clear teaches them to ignore it.
     */
    public static function badge(User $user, string $page): int
    {
        if ($page === 'approvals') {
            $status = $user->hasRole('patron') ? Transaction::PENDING_PATRON : Transaction::PENDING_CHAIR;

            return Transaction::query()->where('status', $status)->where('maker_id', '!=', $user->id)->count();
        }

        if ($page === 'treasury' && $user->hasRole('treasurer')) {
            return Loan::query()->where('status', Loan::REQUESTED)->count()
                + PaymentNotice::query()->where('status', PaymentNotice::OPEN)->count();
        }

        return 0;
    }
}
