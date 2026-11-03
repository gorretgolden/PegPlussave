<?php

namespace App\Models;

use App\Support\PegDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One movement in the PEG books, and the unit the maker-checker rule is built around: nothing here
 * touches a balance until it is posted, and posting needs the Chairperson (plus the Patron for the
 * exceptional ones) to have checked it after somebody else made it.
 */
class Transaction extends Model
{
    use HasFactory;

    public const DEPOSIT = 'deposit';

    public const LOAN_BOOKING = 'loan_booking';

    public const REPAYMENT = 'repayment';

    public const INTEREST_POSTING = 'interest_posting';

    public const INTEREST_PAYOUT = 'interest_payout';

    public const EXIT_SETTLEMENT = 'exit_settlement';

    public const EXTERNAL_LOAN = 'ext_loan';

    public const EXTERNAL_REPAYMENT = 'ext_repayment';

    public const MEMBERSHIP = 'membership';

    public const PENDING_CHAIR = 'pending_chair';

    public const PENDING_PATRON = 'pending_patron';

    public const POSTED = 'posted';

    public const REJECTED = 'rejected';

    public const TYPES = [
        self::DEPOSIT,
        self::LOAN_BOOKING,
        self::REPAYMENT,
        self::INTEREST_POSTING,
        self::INTEREST_PAYOUT,
        self::EXIT_SETTLEMENT,
        self::EXTERNAL_LOAN,
        self::EXTERNAL_REPAYMENT,
        self::MEMBERSHIP,
    ];

    /**
     * What each entry does to the cash PEG holds. A posting moves nothing: it credits a balance
     * the group already owes, so it stays off the cash book.
     */
    public const CASH_EFFECT = [
        self::DEPOSIT => 1,
        self::LOAN_BOOKING => -1,
        self::REPAYMENT => 1,
        self::INTEREST_PAYOUT => -1,
        self::EXIT_SETTLEMENT => -1,
        self::EXTERNAL_LOAN => 1,
        self::EXTERNAL_REPAYMENT => -1,
    ];

    public const TYPE_LABELS = [
        self::DEPOSIT => 'Savings deposit',
        self::LOAN_BOOKING => 'Loan booking',
        self::REPAYMENT => 'Loan repayment',
        self::INTEREST_POSTING => 'Monthly interest posting',
        self::INTEREST_PAYOUT => 'Annual interest payout',
        self::EXIT_SETTLEMENT => 'Exit settlement',
        self::EXTERNAL_LOAN => 'External borrowing',
        self::EXTERNAL_REPAYMENT => 'External loan repayment',
        self::MEMBERSHIP => 'New member registration',
    ];

    public const STATUS_LABELS = [
        self::PENDING_CHAIR => 'Awaiting Chairperson',
        self::PENDING_PATRON => 'Awaiting Patron',
        self::POSTED => 'Posted',
        self::REJECTED => 'Rejected',
    ];

    public const CHANNELS = ['Salary deduction', 'Mobile money', 'Bank deposit', 'Cash'];

    public const PURPOSES = ['School fees', 'Medical', 'Business', 'Rent', 'Asset purchase', 'Emergency', 'Development', 'Other'];

    protected $fillable = [
        'ref', 'type', 'value_date', 'amount', 'member_id', 'loan_id', 'external_loan_id',
        'meta', 'escalated', 'status', 'maker_id', 'maker_role', 'made_at',
    ];

    protected function casts(): array
    {
        return [
            'value_date' => 'date',
            'made_at' => 'datetime',
            'meta' => 'array',
            'escalated' => 'boolean',
            'amount' => 'integer',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function externalLoan()
    {
        return $this->belongsTo(ExternalLoan::class);
    }

    public function maker()
    {
        return $this->belongsTo(User::class, 'maker_id');
    }

    public function checks()
    {
        return $this->hasMany(TransactionCheck::class)->orderBy('checked_at');
    }

    public function notes()
    {
        return $this->hasMany(TransactionNote::class)->orderBy('noted_at');
    }

    public function allocations()
    {
        return $this->hasMany(InterestAllocation::class);
    }

    public function isPending(): bool
    {
        return str_starts_with($this->status, 'pending');
    }

    public function isPosted(): bool
    {
        return $this->status === self::POSTED;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Who the money concerns: the saver, the borrower, the lender, or the savers as a group when a
     * posting or payout is spread across them.
     */
    public function party(): string
    {
        return match (true) {
            (bool) $this->member_id => $this->member?->name ?? '',
            (bool) $this->external_loan_id => $this->externalLoan?->lender ?? '',
            $this->type === self::MEMBERSHIP => (string) ($this->meta['name'] ?? ''),
            $this->type === self::INTEREST_POSTING => count($this->allocations).' savers · '.PegDates::monthLabel((string) ($this->meta['month'] ?? now()->format('Y-m'))),
            $this->type === self::INTEREST_PAYOUT => count($this->allocations).' savers · '.$this->meta['year'],
            default => '',
        };
    }

    public function scopePosted($query)
    {
        return $query->where('status', self::POSTED);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
