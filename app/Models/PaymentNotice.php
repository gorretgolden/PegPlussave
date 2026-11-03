<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A member saying "I have already paid you". It is not a deposit: it is a note to the Treasurer,
 * who then records the real entry. Keeping the two apart is what stops a claim becoming a balance.
 */
class PaymentNotice extends Model
{
    public const OPEN = 'open';

    public const RECORDED = 'recorded';

    public const DISMISSED = 'dismissed';

    protected $fillable = [
        'ref', 'member_id', 'amount', 'paid_on', 'channel', 'reference',
        'month', 'status', 'transaction_id',
    ];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'integer'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::OPEN => 'Waiting for the Treasurer',
            self::RECORDED => 'Recorded',
            self::DISMISSED => 'Dismissed',
            default => $this->status,
        };
    }
}
