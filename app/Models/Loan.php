<?php

namespace App\Models;

use App\Services\LoanLedger;
use App\Support\PegDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    public const REQUESTED = 'requested';

    public const IN_APPROVAL = 'in_approval';

    public const ACTIVE = 'active';

    public const REJECTED = 'rejected';

    public const DECLINED = 'declined';

    protected $fillable = [
        'ref', 'member_id', 'amount', 'months', 'rate', 'method', 'purpose', 'status',
        'requested_by', 'requested_at', 'disbursed_on',
        'appraised_by', 'appraised_at', 'appraisal_decision', 'appraisal_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'months' => 'integer',
            'rate' => 'decimal:2',
            'requested_at' => 'datetime',
            'disbursed_on' => 'date',
            'appraised_at' => 'datetime',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Whose account raised the request. A request is treated as the member's own only when the two
     * numbers line up; a Treasurer booking on someone's behalf is a different kind of entry.
     */
    public function requestedByUser()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function appraisedByUser()
    {
        return $this->belongsTo(User::class, 'appraised_by');
    }

    public function guarantors()
    {
        return $this->belongsToMany(Member::class, 'loan_guarantors');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function postedRepayments()
    {
        return $this->hasMany(Transaction::class)
            ->where('type', Transaction::REPAYMENT)
            ->where('status', Transaction::POSTED)
            ->orderBy('value_date')
            ->orderBy('ref');
    }

    public function booking()
    {
        return $this->hasMany(Transaction::class)->where('type', Transaction::LOAN_BOOKING)->latest('id');
    }

    public function scopeDisbursed($query)
    {
        return $query->whereNotNull('disbursed_on');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::REQUESTED, self::IN_APPROVAL, self::ACTIVE]);
    }

    public function ledger(?string $asOf = null): array
    {
        return LoanLedger::for($this, $asOf);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::REQUESTED => 'Requested',
            self::IN_APPROVAL => 'In approval',
            self::ACTIVE => 'Active',
            self::REJECTED => 'Rejected',
            self::DECLINED => 'Declined',
            default => $this->status,
        };
    }
}
