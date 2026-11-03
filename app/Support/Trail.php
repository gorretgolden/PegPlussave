<?php

namespace App\Support;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\TransactionCheck;
use App\Models\User;

/**
 * The stamp trail: who made an entry, who checked it, and what is still waiting. It is drawn from
 * the check rows themselves, so a trail cannot claim an approval that was never recorded, and an
 * entry that was rejected shows the cross rather than quietly dropping the empty step.
 */
class Trail
{
    /**
     * @return array<int, array{kind: string, verb: string, role: ?string, who: ?string, at: ?string, comment: ?string}>
     */
    public static function forTransaction(Transaction $transaction): array
    {
        $steps = [[
            'kind' => 'done',
            'verb' => 'Made',
            'role' => $transaction->maker_role,
            'who' => self::user($transaction->maker_id),
            'at' => $transaction->made_at?->toDateTimeString(),
            'comment' => null,
        ]];

        return array_merge($steps, self::checkSteps($transaction));
    }

    /**
     * @return array<int, array{kind: string, verb: string, role: ?string, who: ?string, at: ?string, comment: ?string}>
     */
    public static function checkSteps(Transaction $transaction): array
    {
        $steps = [];
        $chair = $transaction->checks->firstWhere('role', 'chair');

        if ($chair) {
            $steps[] = self::step(
                $chair->action === 'rejected' ? 'no' : 'done',
                $chair->action === 'rejected' ? 'Rejected' : 'Checked',
                $chair
            );
        } elseif ($transaction->status !== Transaction::REJECTED) {
            $steps[] = ['kind' => 'wait', 'verb' => 'Check', 'role' => 'chair', 'who' => null, 'at' => null, 'comment' => null];
        }

        $patron = $transaction->checks->firstWhere('role', 'patron');

        if ($transaction->escalated && ! ($chair && $chair->action === 'rejected')) {
            if ($patron) {
                $steps[] = self::step(
                    $patron->action === 'rejected' ? 'no' : 'done',
                    $patron->action === 'rejected' ? 'Rejected' : 'Authorised',
                    $patron
                );
            } elseif ($transaction->status !== Transaction::REJECTED) {
                $steps[] = ['kind' => 'wait', 'verb' => 'Authorise', 'role' => 'patron', 'who' => null, 'at' => null, 'comment' => null];
            }
        }

        return $steps;
    }

    /**
     * A loan is followed from the request to the day the money went out, which may pass through two
     * separate approvals; the trail shows the road it actually took.
     *
     * @return array<int, array{kind: string, verb: string, role: ?string, who: ?string, at: ?string, comment: ?string}>
     */
    public static function forLoan(Loan $loan, ?Transaction $booking = null): array
    {
        $steps = [];
        $askedByMember = $loan->requested_by !== null
            && $loan->requestedByUser?->member_id === $loan->member_id;

        if ($loan->requested_at && $askedByMember) {
            $steps[] = ['kind' => 'done', 'verb' => 'Requested', 'role' => 'member', 'who' => self::member($loan->member_id), 'at' => $loan->requested_at?->toDateTimeString(), 'comment' => null];
        }

        if ($loan->appraised_at) {
            $declined = $loan->appraisal_decision === 'declined';
            $steps[] = [
                'kind' => $declined ? 'no' : 'done',
                'verb' => $declined ? 'Declined' : ($askedByMember ? 'Appraised' : 'Booked'),
                'role' => 'treasurer',
                'who' => self::user($loan->appraised_by),
                'at' => $loan->appraised_at?->toDateTimeString(),
                'comment' => $loan->appraisal_note,
            ];
        } elseif ($loan->status === Loan::REQUESTED) {
            $steps[] = ['kind' => 'wait', 'verb' => 'Appraise', 'role' => 'treasurer', 'who' => null, 'at' => null, 'comment' => null];
        }

        if ($booking) {
            $steps = array_merge($steps, self::checkSteps($booking));
        }

        if ($loan->disbursed_on) {
            $steps[] = ['kind' => 'done', 'verb' => 'Disbursed', 'role' => null, 'who' => null, 'at' => $loan->disbursed_on->toDateString(), 'comment' => null];
        }

        return $steps;
    }

    /**
     * The three dots a table row carries: made, checked, and authorised when the entry needed it.
     *
     * @return array<int, array{kind: string, label: string}>
     */
    public static function mini(Transaction $transaction): array
    {
        $chair = $transaction->checks->firstWhere('role', 'chair');
        $patron = $transaction->checks->firstWhere('role', 'patron');

        $dots = [[
            'kind' => 'done',
            'label' => 'Made by '.PermissionCatalog::label($transaction->maker_role),
        ]];

        $dots[] = $chair
            ? ['kind' => $chair->action === 'rejected' ? 'no' : 'done', 'label' => 'Chairperson']
            : ['kind' => 'wait', 'label' => 'Chairperson'];

        if ($transaction->escalated) {
            $dots[] = $patron
                ? ['kind' => $patron->action === 'rejected' ? 'no' : 'done', 'label' => 'Patron']
                : ['kind' => 'wait', 'label' => 'Patron'];
        }

        return $dots;
    }

    private static function step(string $kind, string $verb, TransactionCheck $check): array
    {
        return [
            'kind' => $kind,
            'verb' => $verb,
            'role' => $check->role,
            'who' => self::user($check->user_id),
            'at' => $check->checked_at?->toDateTimeString(),
            'comment' => $check->comment,
        ];
    }

    /**
     * A trail names people, not identity numbers: a meeting reads "checked by Edina Nakato".
     */
    public static function user(int|null $id): ?string
    {
        return $id === null ? null : User::query()->where('id', $id)->value('name');
    }

    public static function member(int|null $id): ?string
    {
        return $id === null ? null : Member::query()->where('id', $id)->value('name');
    }

    /**
     * The pill a loan is read by. "With Patron" is a state of the paperwork, not of the money, so it
     * is decided from the booking entry rather than from the loan row.
     */
    public static function loanState(Loan $loan, ?array $ledger = null): string
    {
        if ($loan->status === Loan::DECLINED) {
            return 'Declined';
        }

        if ($loan->status === Loan::REJECTED) {
            return 'Rejected';
        }

        if (! $loan->disbursed_on) {
            if ($loan->status === Loan::REQUESTED) {
                return 'Requested';
            }

            $booking = $loan->booking()->first();

            return $booking && $booking->status === Transaction::PENDING_PATRON ? 'With Patron' : 'In approval';
        }

        $ledger ??= $loan->ledger();

        return $ledger['state'];
    }

    public static function loanTone(string $state): string
    {
        return match ($state) {
            'Requested' => 'info',
            'Declined', 'Rejected', 'In arrears' => 'bad',
            'In approval' => 'warn',
            'With Patron' => 'gold',
            'Current' => 'ok',
            default => 'muted',
        };
    }

    public static function transactionTone(string $status): string
    {
        return match ($status) {
            Transaction::POSTED => 'ok',
            Transaction::PENDING_CHAIR => 'warn',
            Transaction::PENDING_PATRON => 'gold',
            Transaction::REJECTED => 'bad',
            default => 'muted',
        };
    }
}
