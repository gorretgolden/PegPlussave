<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ExternalLoan;
use App\Models\InterestAllocation;
use App\Models\Loan;
use App\Models\Member;
use App\Models\PaymentNotice;
use App\Models\Prospect;
use App\Models\Transaction;
use App\Models\User;
use App\Support\PegDates;
use App\Support\Policy;
use App\Support\Refs;
use Illuminate\Support\Facades\DB;

/**
 * The rule the whole book is built on: an officer puts an entry in, and it changes nothing until an
 * officer who did not put it in says it is right. The Chairperson checks everything; anything outside
 * the ordinary course goes one step further to the Patron. Rejecting sends it back with a reason.
 *
 * Posting is where the entry stops being a claim and starts being money, so every consequence of a
 * posted entry lives in this class and nowhere else.
 */
class EntryWorkflow
{
    /**
     * Book a new entry. It always starts as waiting for the Chairperson, made by the signed-in user.
     *
     * @param  array{type:string, amount:int, value_date?:string, member_id?:int|null, loan_id?:int|null, external_loan_id?:int|null, meta?:array, escalated?:bool}  $attrs
     */
    public static function make(array $attrs, User $maker): Transaction
    {
        return DB::transaction(function () use ($attrs, $maker) {
            $type = $attrs['type'];

            // The monthly share is decided the moment the posting is written, so the Chairperson
            // approves the figures the savers will actually see rather than a promise of them. A
            // payout brings its own list, because it pays what earlier postings already credited.
            if ($type === Transaction::INTEREST_POSTING || $type === Transaction::INTEREST_PAYOUT) {
                $attrs['meta']['alloc'] = $type === Transaction::INTEREST_POSTING
                    ? InterestDistribution::allocateToRows((int) ($attrs['meta']['pool'] ?? 0), $attrs['meta']['month'])
                    : ($attrs['meta']['alloc'] ?? []);
                $attrs['amount'] = array_sum(array_column($attrs['meta']['alloc'], 'amount'));
            }

            $transaction = Transaction::create([
                'ref' => Refs::transaction(),
                'type' => $type,
                'value_date' => $attrs['value_date'] ?? now()->toDateString(),
                'amount' => (int) round($attrs['amount']),
                'member_id' => $attrs['member_id'] ?? null,
                'loan_id' => $attrs['loan_id'] ?? null,
                'external_loan_id' => $attrs['external_loan_id'] ?? null,
                'meta' => $attrs['meta'] ?? [],
                'escalated' => (bool) ($attrs['escalated'] ?? false),
                'status' => Transaction::PENDING_CHAIR,
                'maker_id' => $maker->id,
                'maker_role' => $maker->roleKey(),
                'made_at' => now(),
            ]);

            if ($type === Transaction::INTEREST_POSTING) {
                foreach ($attrs['meta']['alloc'] as $row) {
                    InterestAllocation::create([
                        'transaction_id' => $transaction->id,
                        'member_id' => $row['member_id'],
                        'base' => $row['base'] ?? null,
                        'amount' => (int) $row['amount'],
                    ]);
                }
            }

            return $transaction;
        });
    }

    /**
     * The Chairperson's yes. Anything flagged for the Patron stops there and waits; the rest is
     * posted and the books move.
     */
    public static function approve(Transaction $transaction, User $checker, ?string $comment = null): string
    {
        return DB::transaction(function () use ($transaction, $checker, $comment) {
            self::guard($transaction, $checker);

            if ($checker->hasRole('admin') || $checker->hasRole('patron')) {
                abort_unless($transaction->status === Transaction::PENDING_PATRON, 403, 'This entry is not with the Patron.');
                $action = 'authorised';
            } else {
                abort_unless($transaction->status === Transaction::PENDING_CHAIR, 403, 'This entry is not with the Chairperson.');
                $action = 'approved';
            }

            $transaction->checks()->create([
                'user_id' => $checker->id,
                'role' => $checker->roleKey(),
                'action' => $action,
                'comment' => $comment ?: null,
                'checked_at' => now(),
            ]);

            if ($action === 'approved' && $transaction->escalated) {
                $transaction->update(['status' => Transaction::PENDING_PATRON]);
                self::audit($checker, "Approved {$transaction->ref} and sent it to the Patron", $transaction->ref);

                return "{$transaction->ref} approved and sent to the Patron";
            }

            $transaction->update(['status' => Transaction::POSTED]);
            self::post($transaction);
            self::audit($checker, "{$action} and posted {$transaction->ref}", $transaction->ref);

            return "{$transaction->ref} ".($action === 'authorised' ? 'authorised' : 'approved').' and posted';
        });
    }

    /**
     * No, with a reason. The maker is told what to fix, and whatever the entry had already touched is
     * put back the way it was.
     */
    public static function reject(Transaction $transaction, User $checker, string $comment): string
    {
        return DB::transaction(function () use ($transaction, $checker, $comment) {
            self::guard($transaction, $checker);
            abort_if(trim($comment) === '', 422, 'Add a reason so the Treasurer knows what to fix.');

            $transaction->checks()->create([
                'user_id' => $checker->id,
                'role' => $checker->roleKey(),
                'action' => 'rejected',
                'comment' => trim($comment),
                'checked_at' => now(),
            ]);

            $transaction->update(['status' => Transaction::REJECTED]);
            self::undo($transaction);
            self::audit($checker, "Rejected {$transaction->ref}", $transaction->ref);

            return "{$transaction->ref} rejected and returned to the maker";
        });
    }

    /**
     * A member may not check their own entry, and a checker may only act on what is in front of them.
     */
    private static function guard(Transaction $transaction, User $checker): void
    {
        abort_if($transaction->maker_id === $checker->id, 422, 'You made this entry, so another officer must check it.');
        abort_unless($transaction->isPending(), 403, 'This entry has already been settled.');
    }

    /**
     * What a posted entry does to the rest of the group's records.
     */
    public static function post(Transaction $transaction): void
    {
        $today = now()->toDateString();

        match ($transaction->type) {
            Transaction::LOAN_BOOKING => self::postLoanBooking($transaction, $today),
            Transaction::EXTERNAL_LOAN => self::postExternalLoan($transaction),
            Transaction::MEMBERSHIP => self::postMembership($transaction),
            Transaction::EXIT_SETTLEMENT => self::postExit($transaction, $today),
            default => null,
        };
    }

    private static function postLoanBooking(Transaction $transaction, string $today): void
    {
        $loan = $transaction->loan;
        if (! $loan) {
            return;
        }

        $loan->update(['status' => Loan::ACTIVE, 'disbursed_on' => $today]);
        $transaction->update(['value_date' => $today]);
    }

    private static function postExternalLoan(Transaction $transaction): void
    {
        $transaction->externalLoan?->update([
            'status' => ExternalLoan::ACTIVE,
            'received_on' => $transaction->value_date->toDateString(),
        ]);
    }

    /**
     * A new member is only created once the registration clears. Until then the name is a claim on a
     * transaction, and the PEG number that will belong to them is not taken up.
     */
    private static function postMembership(Transaction $transaction): void
    {
        $meta = $transaction->meta ?? [];

        $member = Member::create([
            'code' => Refs::member(),
            'name' => $meta['name'],
            'department' => $meta['dept'],
            'monthly_pledge' => (int) $meta['monthly'],
            'joined_on' => $meta['joined'] ?? $transaction->value_date->toDateString(),
            'extension' => $meta['ext'] ?? '',
            'status' => 'active',
        ]);

        $username = Refs::username($meta['name']);
        User::create([
            'name' => $meta['name'],
            'username' => $username,
            'password' => 'peg2026',
            'member_id' => $member->id,
            'status' => 'active',
        ])->assignRole('member');

        if (! empty($meta['prospect_id'])) {
            Prospect::where('id', $meta['prospect_id'])->update(['stage' => 'Member']);
        }

        $transaction->update(['meta' => array_merge($meta, [
            'new_member_id' => $member->id,
            'new_code' => $member->code,
            'username' => $username,
        ])]);
    }

    private static function postExit(Transaction $transaction, string $today): void
    {
        $meta = $transaction->meta ?? [];
        $member = $transaction->member;

        $member?->update([
            'status' => 'left',
            'exit_on' => $meta['exit_date'] ?? $today,
            'exit_flagged' => false,
        ]);

        // The loan does not disappear with the borrower: the part of the settlement it covers is
        // booked as a repayment the same day, carrying the number of the entry that paid for it.
        $offset = (int) ($meta['offset'] ?? 0);
        if ($offset > 0 && ! empty($meta['loan_id'])) {
            $repayment = Transaction::create([
                'ref' => Refs::transaction(),
                'type' => Transaction::REPAYMENT,
                'value_date' => $today,
                'amount' => $offset,
                'member_id' => $transaction->member_id,
                'loan_id' => $meta['loan_id'],
                'meta' => ['channel' => 'Offset from savings', 'ref' => $transaction->ref, 'via_exit' => $transaction->ref],
                'escalated' => false,
                'status' => Transaction::POSTED,
                'maker_id' => $transaction->maker_id,
                'maker_role' => $transaction->maker_role,
                'made_at' => $transaction->made_at,
            ]);

            foreach ($transaction->checks as $check) {
                $repayment->checks()->create($check->only(['user_id', 'role', 'action', 'comment', 'checked_at']));
            }
        }
    }

    /**
     * Undo what a rejection takes back. Only entries that were never posted reach here, so this is
     * the state a maker sees again, not a reversal of money that already moved.
     */
    public static function undo(Transaction $transaction): void
    {
        match ($transaction->type) {
            Transaction::LOAN_BOOKING => Loan::where('id', $transaction->loan_id)->update(['status' => Loan::REJECTED]),
            Transaction::EXTERNAL_LOAN => ExternalLoan::where('id', $transaction->external_loan_id)->update(['status' => ExternalLoan::REJECTED]),
            Transaction::MEMBERSHIP => Prospect::where('id', $transaction->meta['prospect_id'] ?? null)->update(['stage' => 'Interested']),
            Transaction::DEPOSIT => PaymentNotice::query()
                ->where('transaction_id', $transaction->id)
                ->update(['status' => PaymentNotice::OPEN, 'transaction_id' => null]),
            default => null,
        };
    }

    /**
     * How much is already waiting against a loan, so a second repayment cannot promise the same money.
     */
    public static function pendingRepayments(int $loanId): int
    {
        return (int) Transaction::query()
            ->where('loan_id', $loanId)
            ->where('type', Transaction::REPAYMENT)
            ->where('status', 'like', 'pending%')
            ->sum('amount');
    }

    /**
     * Whether an entry of this size goes to the Patron as well. Kept here because the answer must be
     * the same on the form that offers it and the desk that receives it.
     */
    public static function escalates(int $amount): bool
    {
        return $amount > (int) Policy::get('patron_threshold');
    }

    public static function monthEnd(string $yearMonth): string
    {
        return PegDates::monthEnd($yearMonth);
    }

    private static function audit(User $user, string $action, string $ref): void
    {
        ActivityLog::record($user, $action, $ref);
    }
}
