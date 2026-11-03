<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterestAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['transaction_id', 'member_id', 'base', 'amount'];

    protected function casts(): array
    {
        return ['base' => 'integer', 'amount' => 'integer'];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
