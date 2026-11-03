<?php

namespace App\Models;

use App\Services\LoanSchedule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Money PEG borrows from outside the group, and the only way the fund covers a month when member
 * savings run short. It always needs the Patron: the group is taking a debt on behalf of everyone.
 */
class ExternalLoan extends Model
{
    use HasFactory;

    public const IN_APPROVAL = 'in_approval';

    public const ACTIVE = 'active';

    public const REJECTED = 'rejected';

    public const CLOSED = 'closed';

    protected $fillable = ['ref', 'lender', 'amount', 'rate', 'months', 'purpose', 'status', 'received_on'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'rate' => 'decimal:2',
            'months' => 'integer',
            'received_on' => 'date',
        ];
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function postedRepayments()
    {
        return $this->hasMany(Transaction::class)
            ->where('type', Transaction::EXTERNAL_REPAYMENT)
            ->where('status', Transaction::POSTED)
            ->orderBy('value_date');
    }

    /**
     * Principal and interest split out of each repayment, and what is still owing the lender.
     */
    public function info(): array
    {
        $repayments = $this->postedRepayments()->get();
        $principalPaid = (int) $repayments->sum(fn (Transaction $t) => (int) ($t->meta['principal'] ?? 0));
        $interestPaid = (int) $repayments->sum(fn (Transaction $t) => (int) ($t->meta['interest'] ?? 0));

        $outstanding = $this->status === self::ACTIVE
            ? max(0, (int) $this->amount - $principalPaid)
            : 0;

        $instalmentPrincipal = (int) round($this->amount / $this->months);
        $instalmentInterest = (int) round($this->amount * $this->rate / 100);

        return [
            'principal_paid' => $principalPaid,
            'interest_paid' => $interestPaid,
            'outstanding' => $outstanding,
            'next_principal' => min($instalmentPrincipal, $outstanding),
            'next_interest' => $outstanding > 0 ? $instalmentInterest : 0,
            'next_due' => $this->received_on && $outstanding > 0
                ? \App\Support\PegDates::addMonths($this->received_on->toDateString(), $repayments->count() + 1)
                : null,
            'instalments_paid' => $repayments->count(),
            'instalment_principal' => $instalmentPrincipal,
            'instalment_interest' => $instalmentInterest,
            'total_interest' => $instalmentInterest * $this->months,
        ];
    }
}
