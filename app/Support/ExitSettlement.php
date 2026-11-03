<?php

namespace App\Support;

use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanLedger;

/**
 * What the group owes a member who leaves. Their own savings, the interest credited to them and not
 * yet taken, and whatever is still owing on a loan they hold set off against it. The set-off is why
 * an exit cannot be booked from memory: a borrower who leaves with a live loan takes the difference,
 * not the whole pot.
 */
class ExitSettlement
{
    public static function calculate(Member|int $member, ?string $asOf = null): array
    {
        $member = $member instanceof Member ? $member : Member::findOrFail($member);
        $asOf = $asOf ?: now()->toDateString();

        $balance = TreasuryPosition::memberBalances($asOf)[$member->id] ?? ['balance' => 0, 'interest' => 0];
        $savings = max(0, (int) $balance['balance']);
        $interest = max(0, (int) $balance['interest']);

        $running = $member->loans()
            ->whereNotNull('disbursed_on')
            ->get()
            ->first(fn (Loan $loan) => LoanLedger::balance($loan, $asOf) > 0);

        $offset = $running ? LoanLedger::balance($running, $asOf) : 0;

        return [
            'member' => $member,
            'savings' => $savings,
            'interest' => $interest,
            'offset' => $offset,
            'loan_ref' => $running?->ref,
            'loan_id' => $running?->id,
            'net' => $savings + $interest - $offset,
            'paid_out' => $savings + $interest,
        ];
    }

    /**
     * A settlement the books cannot pay: the loan is bigger than everything they saved. The
     * difference is recovered first, and the screen says so rather than letting a negative through.
     */
    public static function short(array $settlement): bool
    {
        return $settlement['net'] < 0;
    }
}
